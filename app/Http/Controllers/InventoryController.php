<?php

namespace App\Http\Controllers;

use App\Support\ClinicContext;

use App\Models\InventoryItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class InventoryController extends Controller
{
    public function index()
    {
        $productos = InventoryItem::orderBy('material')->get();

        $alertas = $productos->filter(fn($item) => $item->needsRestock())->values();
        $costoTotalInventario = $productos->sum(
            fn ($item) => $item->inventoryValue()
        );
        $costosPorProveedor = $productos
            ->groupBy(fn ($item) => trim((string) $item->proveedor) ?: 'Sin proveedor')
            ->map(fn ($items) => [
                'total_materiales' => $items->count(),
                'costo_promedio' => $items->avg(fn ($item) => (float) $item->costo) ?? 0,
            ]);

        return view('inventario.index', compact('productos', 'alertas', 'costoTotalInventario', 'costosPorProveedor'));
    }

    public function create()
    {
        return view('inventario.crear');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'material' => ['required', 'string', 'max:255', app(ClinicContext::class)->unique('inventory_items', 'material')],
            'stock' => 'required|integer|min:0',
            'reposicion' => 'required|integer|min:0',
            'costo' => 'required|numeric|min:0',
            'proveedor' => 'nullable|string|max:255',
        ]);

        $validated['proveedor'] = $this->normalizeProvider($validated['proveedor'] ?? null);

        InventoryItem::create($validated);

        return redirect()->route('inventario')->with('success', 'Material agregado correctamente.');
    }

    public function edit($id)
    {
        $item = InventoryItem::findOrFail($id);

        return view('inventario.editar', ['item' => $item->toArray()]);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'material' => ['required', 'string', 'max:255', app(ClinicContext::class)->unique('inventory_items', 'material')->ignore($id)],
            'stock' => 'required|integer|min:0',
            'reposicion' => 'required|integer|min:0',
            'costo' => 'required|numeric|min:0',
            'proveedor' => 'nullable|string|max:255',
        ]);

        $validated['proveedor'] = $this->normalizeProvider($validated['proveedor'] ?? null);

        $item = InventoryItem::findOrFail($id);
        $item->update($validated);

        return redirect()->route('inventario')->with('success', 'Material actualizado correctamente.');
    }

    public function destroy($id)
    {
        $item = InventoryItem::find($id);

        if ($item) {
            $item->delete();
        }

        return redirect()->route('inventario')->with('success', 'Material eliminado correctamente.');
    }

    public function exportPdf(Request $request)
    {
        $report = $this->buildReport((string) $request->query('tipo', 'completo'));
        $filename = 'inventario-'.$report['type'].'-dentiflow-'.now()->format('Y-m-d').'.pdf';

        return Pdf::loadView('inventario.pdf', [
            ...$report,
            'generatedAt' => now(),
            'generatedBy' => $request->session()->get('access_name', 'Usuario DentiFlow'),
        ])->setPaper('letter', 'landscape')->download($filename);
    }

    public function exportExcel(Request $request)
    {
        $report = $this->buildReport((string) $request->query('tipo', 'completo'));
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('DentiFlow')
            ->setTitle($report['title'])
            ->setSubject('Reporte de inventario');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Inventario');
        $lastColumn = Coordinate::stringFromColumnIndex(count($report['headers']));
        $sheet->mergeCells("A1:{$lastColumn}1");
        $sheet->setCellValue('A1', $report['title']);
        $sheet->mergeCells("A2:{$lastColumn}2");
        $sheet->setCellValue('A2', 'Generado el '.now()->format('d/m/Y H:i').' por '.$request->session()->get('access_name', 'Usuario DentiFlow'));
        $sheet->mergeCells("A3:{$lastColumn}3");
        $sheet->setCellValue('A3', $report['description']);
        $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'font' => ['bold' => true, 'size' => 18, 'color' => ['RGB' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['RGB' => '9700CF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getStyle("A2:{$lastColumn}3")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(30);

        $headerRow = 5;
        foreach ($report['headers'] as $index => $header) {
            $column = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValue($column.$headerRow, $report['labels'][$header] ?? ucfirst($header));
        }
        $sheet->getStyle("A{$headerRow}:{$lastColumn}{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['RGB' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['RGB' => '512569']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['RGB' => 'EAD2FB']]],
        ]);

        $rowNumber = 6;
        foreach ($report['rows'] as $row) {
            foreach ($report['headers'] as $index => $header) {
                $column = Coordinate::stringFromColumnIndex($index + 1);
                $value = $row[$header] ?? '';
                $numericColumns = ['stock', 'reposicion', 'faltante', 'materiales', 'unidades', 'costo_unitario', 'costo_promedio', 'valor'];
                if (in_array($header, $numericColumns, true)) {
                    $sheet->setCellValueExplicit($column.$rowNumber, (float) $value, DataType::TYPE_NUMERIC);
                } else {
                    $sheet->setCellValueExplicit($column.$rowNumber, (string) $value, DataType::TYPE_STRING);
                }
                if (in_array($header, $report['moneyColumns'], true)) {
                    $sheet->getStyle($column.$rowNumber)->getNumberFormat()->setFormatCode('"$"#,##0.00');
                } elseif (in_array($header, ['stock', 'reposicion', 'faltante', 'materiales', 'unidades'], true)) {
                    $sheet->getStyle($column.$rowNumber)->getNumberFormat()->setFormatCode('#,##0');
                }
            }
            $rowNumber++;
        }

        $lastDataRow = max($headerRow, $rowNumber - 1);
        if ($report['rows']->isNotEmpty()) {
            $sheet->getStyle("A6:{$lastColumn}{$lastDataRow}")->applyFromArray([
                'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['RGB' => 'E7DDEC']]],
            ]);
            $sheet->setAutoFilter("A5:{$lastColumn}{$lastDataRow}");
        }

        if (in_array('valor', $report['headers'], true) && $report['rows']->isNotEmpty()) {
            $totalRow = $rowNumber + 1;
            $valueColumn = Coordinate::stringFromColumnIndex(array_search('valor', $report['headers'], true) + 1);
            $labelColumn = Coordinate::stringFromColumnIndex(max(1, array_search('valor', $report['headers'], true)));
            $sheet->setCellValue($labelColumn.$totalRow, 'VALOR TOTAL');
            $sheet->setCellValue($valueColumn.$totalRow, "=SUM({$valueColumn}6:{$valueColumn}{$lastDataRow})");
            $sheet->getStyle($labelColumn.$totalRow.':'.$valueColumn.$totalRow)->getFont()->setBold(true);
            $sheet->getStyle($valueColumn.$totalRow)->getNumberFormat()->setFormatCode('"$"#,##0.00');
        }

        $widths = [
            'material' => 30, 'stock' => 14, 'reposicion' => 18, 'faltante' => 18,
            'costo_unitario' => 18, 'proveedor' => 28, 'estado' => 20, 'valor' => 20,
            'materiales' => 16, 'unidades' => 18, 'costo_promedio' => 20,
        ];
        foreach ($report['headers'] as $index => $header) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index + 1))->setWidth($widths[$header] ?? 18);
        }
        $sheet->freezePane('A6');
        $sheet->setSelectedCell('A1');

        $filename = 'inventario-'.$report['type'].'-dentiflow-'.now()->format('Y-m-d').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    private function buildReport(string $type): array
    {
        $type = in_array($type, ['completo', 'alertas', 'proveedores', 'valor'], true) ? $type : 'completo';
        $items = InventoryItem::orderBy('material')->get();
        $labels = [
            'material' => 'Material', 'stock' => 'Stock disponible', 'reposicion' => 'Nivel de reposición',
            'faltante' => 'Unidades sugeridas', 'costo_unitario' => 'Costo unitario', 'proveedor' => 'Proveedor',
            'estado' => 'Estado', 'valor' => 'Valor estimado', 'materiales' => 'Materiales',
            'unidades' => 'Unidades en stock', 'costo_promedio' => 'Costo promedio',
        ];

        [$title, $description, $headers, $rows] = match ($type) {
            'alertas' => [
                'Reporte de productos por reponer',
                'Materiales con cinco unidades o menos que requieren seguimiento.',
                ['material', 'stock', 'reposicion', 'faltante', 'proveedor', 'estado'],
                $items->filter->needsRestock()->map(fn (InventoryItem $item) => [
                    'material' => $item->material,
                    'stock' => $item->stock,
                    'reposicion' => $item->reposicion,
                    'faltante' => max(0, $item->reposicion - $item->stock),
                    'proveedor' => trim((string) $item->proveedor) ?: 'Sin proveedor',
                    'estado' => $item->stockStatusLabel(),
                ])->values(),
            ],
            'proveedores' => [
                'Reporte de inventario por proveedor',
                'Resumen de materiales, unidades, costos y valor estimado por proveedor.',
                ['proveedor', 'materiales', 'unidades', 'costo_promedio', 'valor'],
                $items->groupBy(fn (InventoryItem $item) => trim((string) $item->proveedor) ?: 'Sin proveedor')
                    ->map(fn ($providerItems, $provider) => [
                        'proveedor' => $provider,
                        'materiales' => $providerItems->count(),
                        'unidades' => $providerItems->sum('stock'),
                        'costo_promedio' => (float) $providerItems->avg(fn (InventoryItem $item) => (float) $item->costo),
                        'valor' => $providerItems->sum(fn (InventoryItem $item) => $item->inventoryValue()),
                    ])->sortBy('proveedor')->values(),
            ],
            'valor' => [
                'Reporte de valor del inventario',
                'Valor estimado de cada material calculado con el stock y costo unitario actuales.',
                ['material', 'stock', 'costo_unitario', 'valor', 'proveedor'],
                $items->map(fn (InventoryItem $item) => [
                    'material' => $item->material,
                    'stock' => $item->stock,
                    'costo_unitario' => (float) $item->costo,
                    'valor' => $item->inventoryValue(),
                    'proveedor' => trim((string) $item->proveedor) ?: 'Sin proveedor',
                ]),
            ],
            default => [
                'Reporte de inventario completo',
                'Estado actual de todos los materiales registrados en el inventario.',
                ['material', 'stock', 'reposicion', 'costo_unitario', 'proveedor', 'estado', 'valor'],
                $items->map(fn (InventoryItem $item) => [
                    'material' => $item->material,
                    'stock' => $item->stock,
                    'reposicion' => $item->reposicion,
                    'costo_unitario' => (float) $item->costo,
                    'proveedor' => trim((string) $item->proveedor) ?: 'Sin proveedor',
                    'estado' => $item->stockStatusLabel(),
                    'valor' => $item->inventoryValue(),
                ]),
            ],
        };

        return [
            'type' => $type,
            'title' => $title,
            'description' => $description,
            'headers' => $headers,
            'labels' => $labels,
            'moneyColumns' => ['costo_unitario', 'costo_promedio', 'valor'],
            'rows' => $rows,
            'summary' => [
                'records' => $rows->count(),
                'units' => $type === 'proveedores' ? $rows->sum('unidades') : $rows->sum('stock'),
                'value' => $type === 'alertas' ? null : $rows->sum('valor'),
            ],
        ];
    }

    private function normalizeProvider(?string $provider): string
    {
        return trim((string) $provider) ?: 'Sin proveedor';
    }
}
