<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Appointment;
use App\Models\Dentist;
use App\Models\Patient;
use App\Models\Treatment;
use App\Models\User;
use App\Services\AppointmentCompletionService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $this->completeOverdueAppointments($request);

        $periodo = $request->query('periodo', 'Ultimos 6 meses');
        $desde = $request->query('desde');
        $hasta = $request->query('hasta');
        $fecha = $request->query('fecha');
        $mes = $request->query('mes');

        [$from, $to, $periodoLabel, $hasData] = $this->resolvePeriod($periodo, $desde, $hasta, $fecha, $mes);
        $isDoctor = $request->session()->get('access_role') === 'doctor';
        $doctorDentist = $isDoctor ? $this->sessionDentist($request) : null;

        $appointments = $this->scopeAppointmentsForRole(
            Appointment::with('dentist')
                ->whereDate('date', '>=', $from)
                ->whereDate('date', '<=', $to),
            $isDoctor,
            $doctorDentist
        )->get();

        $treatments = $this->scopeTreatmentsForRole(
            Treatment::with(['patient', 'appointment.dentist'])
                ->whereDate('fecha', '>=', $from)
                ->whereDate('fecha', '<=', $to),
            $isDoctor,
            $doctorDentist
        )->get();
        $patientsCount = $appointments->pluck('patient_id')
            ->merge($treatments->pluck('patient_id'))
            ->filter()
            ->unique()
            ->count();

        $ganancias = $treatments->where('estado', 'Realizado')->sum('costo');
        $citas = $appointments->count();
        $tratamientos = $treatments->count();

        $previousRange = $this->previousPeriodRange($from, $to);
        $previousAppointmentsCollection = $this->scopeAppointmentsForRole(
            Appointment::whereDate('date', '>=', $previousRange[0])
                ->whereDate('date', '<=', $previousRange[1]),
            $isDoctor,
            $doctorDentist
        )->get();
        $previousTreatmentsCollection = $this->scopeTreatmentsForRole(
            Treatment::whereDate('fecha', '>=', $previousRange[0])
                ->whereDate('fecha', '<=', $previousRange[1]),
            $isDoctor,
            $doctorDentist
        )->get();
        $previousAppointments = $previousAppointmentsCollection->count();
        $previousTreatments = $previousTreatmentsCollection->count();
        $previousGanancias = $previousTreatmentsCollection->where('estado', 'Realizado')->sum('costo');
        $previousPatients = $previousAppointmentsCollection->pluck('patient_id')
            ->merge($previousTreatmentsCollection->pluck('patient_id'))
            ->filter()
            ->unique()
            ->count();

        $reportes = [
            'ganancias' => ['display' => '$' . number_format($ganancias, 0, ',', '.')],
            'pacientes' => ['display' => $patientsCount],
            'citas' => ['display' => $citas],
            'tratamientos' => ['display' => $tratamientos],
        ];

        $trends = [
            'ganancias' => $this->buildTrend($ganancias, $previousGanancias, 'Ganancias'),
            'pacientes' => $this->buildTrend($patientsCount, $previousPatients, 'Pacientes'),
            'citas' => $this->buildTrend($citas, $previousAppointments, 'Citas'),
            'tratamientos' => $this->buildTrend($tratamientos, $previousTreatments, 'Tratamientos'),
        ];

        $distribution = $treatments
            ->groupBy('tipo')
            ->map(fn ($items, $tipo) => [
                'tratamiento' => $tipo,
                'porcentaje' => (int) round(($items->count() / max($tratamientos, 1)) * 100),
            ])
            ->values()
            ->all();

        $treatmentCostBreakdown = $treatments
            ->where('estado', 'Realizado')
            ->groupBy('tratamiento')
            ->map(fn ($items, $name) => [
                'tratamiento' => $name,
                'cantidad' => $items->count(),
            ])
            // Conserva los seis mas realizados y los presenta de menor a mayor.
            ->sortBy([
                ['cantidad', 'desc'],
                ['tratamiento', 'asc'],
            ])
            ->take(6)
            ->sortBy([
                ['cantidad', 'asc'],
                ['tratamiento', 'asc'],
            ])
            ->values()
            ->all();
        $maxTreatmentCount = max(1, collect($treatmentCostBreakdown)->max('cantidad') ?: 0);

        $topDentistas = $appointments
            ->groupBy(fn ($appointment) => $appointment->dentist?->nombre ?? 'Sin dentista')
            ->map(function ($appointments, $dentista) use ($treatments) {
                $appointmentIds = $appointments->pluck('id')->all();
                $ganancias = $treatments
                    ->whereIn('appointment_id', $appointmentIds)
                    ->where('estado', 'Realizado')
                    ->sum('costo');

                return [
                    'dentista' => $dentista,
                    'citas' => $appointments->count(),
                    'ganancias' => $ganancias,
                ];
            })
            ->sortByDesc('citas')
            ->values()
            ->all();

        $grafico = $this->buildMonthlyEarnings($from, $to, $treatments);
        $maxRevenue = max(1, collect($grafico)->max('valor') ?: 0);
        $appointmentGrowth = $this->buildTrend($citas, $previousAppointments, 'Citas');
        $quickReadings = [
            // Lecturas derivadas del periodo actual; evitan valores fijos en el reporte.
            'averageEarningsPerAppointment' => $citas > 0 ? round($ganancias / $citas) : 0,
            'monthlyAppointmentGrowth' => ($appointmentGrowth['positive'] ? '+' : '-').$appointmentGrowth['percent'].'%',
            'activeTreatments' => Treatment::where('estado', 'Activo')->count(),
        ];

        return view('reportes.index', compact(
            'reportes',
            'trends',
            'grafico',
            'maxRevenue',
            'quickReadings',
            'distribution',
            'topDentistas',
            'treatmentCostBreakdown',
            'maxTreatmentCount',
            'hasData',
            'periodo',
            'periodoLabel',
            'from',
            'to',
            'desde',
            'hasta',
            'fecha',
            'mes'
        ));
    }

    public function export(Request $request)
    {
        $this->completeOverdueAppointments($request);

        $periodo = $request->query('periodo', 'Ultimos 6 meses');
        [$from, $to] = $this->resolvePeriod(
            $periodo,
            $request->query('desde'),
            $request->query('hasta'),
            $request->query('fecha'),
            $request->query('mes')
        );
        $reportType = in_array($request->query('reporte', 'tratamientos'), ['ganancias', 'citas', 'pacientes', 'tratamientos'], true)
            ? $request->query('reporte', 'tratamientos')
            : 'tratamientos';

        $isDoctor = $request->session()->get('access_role') === 'doctor';
        $doctorDentist = $isDoctor ? $this->sessionDentist($request) : null;

        $appointments = $this->scopeAppointmentsForRole(
            Appointment::with(['patient', 'dentist'])
                ->whereDate('date', '>=', $from)
                ->whereDate('date', '<=', $to)
                ->orderBy('date')
                ->orderBy('time'),
            $isDoctor,
            $doctorDentist
        )->get();

        $treatments = $this->scopeTreatmentsForRole(
            Treatment::with(['patient', 'appointment.dentist'])
                ->whereDate('fecha', '>=', $from)
                ->whereDate('fecha', '<=', $to)
                ->orderBy('fecha'),
            $isDoctor,
            $doctorDentist
        )->get();
        $totalEarnings = (float) $treatments->where('estado', 'Realizado')->sum('costo');

        [$headers, $rows] = match ($reportType) {
            'ganancias' => [
                ['fecha', 'paciente', 'tratamiento', 'dentista', 'ganancia'],
                $treatments
                    ->where('estado', 'Realizado')
                    ->map(fn (Treatment $treatment) => [
                        'fecha' => $treatment->fecha?->toDateString(),
                        'paciente' => $treatment->patient?->nombre ?? $treatment->paciente,
                        'tratamiento' => $treatment->tratamiento,
                        'dentista' => $treatment->appointment?->dentist?->nombre ?? 'Sin dentista',
                        'ganancia' => (float) $treatment->costo,
                    ])
                    ->values(),
            ],
            'citas' => [
                ['fecha', 'hora', 'paciente', 'tipo', 'estado', 'dentista'],
                $appointments->map(fn (Appointment $appointment) => [
                    'fecha' => $appointment->date?->toDateString(),
                    'hora' => $appointment->time,
                    'paciente' => $appointment->patient?->nombre ?? 'Sin paciente',
                    'tipo' => trim((string) $appointment->procedimiento_extra) !== ''
                        ? $appointment->tipo.' + '.$appointment->procedimiento_extra
                        : $appointment->tipo,
                    'estado' => $appointment->estado,
                    'dentista' => $appointment->dentist?->nombre ?? 'Sin dentista',
                ]),
            ],
            'pacientes' => [
                ['paciente', 'telefono', 'citas', 'tratamientos', 'ganancias'],
                $appointments
                    ->pluck('patient')
                    ->merge($treatments->pluck('patient'))
                    ->filter()
                    ->unique('id')
                    ->map(fn (Patient $patient) => [
                        'paciente' => $patient->nombre,
                        'telefono' => $patient->telefono,
                        'citas' => $appointments->where('patient_id', $patient->id)->count(),
                        'tratamientos' => $treatments->where('patient_id', $patient->id)->count(),
                        'ganancias' => (float) $treatments->where('patient_id', $patient->id)->where('estado', 'Realizado')->sum('costo'),
                    ])
                    ->values(),
            ],
            default => [
                ['fecha', 'paciente', 'tratamiento', 'tipo', 'estado', 'costo', 'dentista'],
                $treatments->map(fn (Treatment $treatment) => [
                'fecha' => $treatment->fecha?->toDateString(),
                'paciente' => $treatment->patient?->nombre ?? $treatment->paciente,
                'tratamiento' => $treatment->tratamiento,
                'tipo' => $treatment->tipo,
                'estado' => $treatment->estado,
                'costo' => (float) $treatment->costo,
                'dentista' => $treatment->appointment?->dentist?->nombre ?? 'Sin dentista',
                ]),
            ],
        };

        $titles = [
            'ganancias' => 'Reporte de ganancias',
            'citas' => 'Reporte de citas',
            'pacientes' => 'Reporte de pacientes',
            'tratamientos' => 'Reporte de tratamientos',
        ];
        $columnLabels = [
            'fecha' => 'Fecha',
            'hora' => 'Hora',
            'paciente' => 'Paciente',
            'telefono' => 'Telefono',
            'tratamiento' => 'Tratamiento',
            'tipo' => 'Tipo',
            'estado' => 'Estado',
            'dentista' => 'Dentista',
            'citas' => 'Citas',
            'tratamientos' => 'Tratamientos',
            'ganancia' => 'Ganancia',
            'ganancias' => 'Ganancias',
            'costo' => 'Costo',
        ];
        $moneyColumns = ['ganancia', 'ganancias', 'costo'];
        $title = $titles[$reportType];
        $filename = 'reporte-'.$reportType.'-dentiflow-'.$from.'-'.$to.'.pdf';

        return Pdf::loadView('reportes.pdf', [
            'title' => $title,
            'reportType' => $reportType,
            'headers' => $headers,
            'columnLabels' => $columnLabels,
            'moneyColumns' => $moneyColumns,
            'rows' => $rows,
            'from' => $from,
            'to' => $to,
            'generatedAt' => now(),
            'generatedBy' => $request->session()->get('access_name', 'Usuario DentiFlow'),
            'totalEarnings' => $totalEarnings,
        ])->setPaper('letter', 'landscape')->download($filename);
    }

    public function exportExcel(Request $request)
    {
        $this->completeOverdueAppointments($request);

        $periodo = $request->query('periodo', 'Ultimos 6 meses');
        [$from, $to] = $this->resolvePeriod(
            $periodo,
            $request->query('desde'),
            $request->query('hasta'),
            $request->query('fecha'),
            $request->query('mes')
        );
        $reportType = in_array($request->query('reporte', 'tratamientos'), ['ganancias', 'citas', 'pacientes', 'tratamientos'], true)
            ? $request->query('reporte', 'tratamientos')
            : 'tratamientos';
        $isDoctor = $request->session()->get('access_role') === 'doctor';
        $doctorDentist = $isDoctor ? $this->sessionDentist($request) : null;
        $appointments = $this->scopeAppointmentsForRole(
            Appointment::with(['patient', 'dentist'])
                ->whereDate('date', '>=', $from)
                ->whereDate('date', '<=', $to)
                ->orderBy('date')->orderBy('time'),
            $isDoctor,
            $doctorDentist
        )->get();
        $treatments = $this->scopeTreatmentsForRole(
            Treatment::with(['patient', 'appointment.dentist'])
                ->whereDate('fecha', '>=', $from)
                ->whereDate('fecha', '<=', $to)
                ->orderBy('fecha'),
            $isDoctor,
            $doctorDentist
        )->get();
        $totalEarnings = (float) $treatments->where('estado', 'Realizado')->sum('costo');

        [$headers, $rows] = match ($reportType) {
            'ganancias' => [
                ['fecha', 'paciente', 'tratamiento', 'dentista', 'ganancia'],
                $treatments->where('estado', 'Realizado')->map(fn (Treatment $treatment) => [
                    'fecha' => $treatment->fecha?->toDateString(),
                    'paciente' => $treatment->patient?->nombre ?? $treatment->paciente,
                    'tratamiento' => $treatment->tratamiento,
                    'dentista' => $treatment->appointment?->dentist?->nombre ?? 'Sin dentista',
                    'ganancia' => (float) $treatment->costo,
                ])->values(),
            ],
            'citas' => [
                ['fecha', 'hora', 'paciente', 'tipo', 'estado', 'dentista'],
                $appointments->map(fn (Appointment $appointment) => [
                    'fecha' => $appointment->date?->toDateString(),
                    'hora' => $appointment->time,
                    'paciente' => $appointment->patient?->nombre ?? 'Sin paciente',
                    'tipo' => trim((string) $appointment->procedimiento_extra) !== '' ? $appointment->tipo.' + '.$appointment->procedimiento_extra : $appointment->tipo,
                    'estado' => $appointment->estado,
                    'dentista' => $appointment->dentist?->nombre ?? 'Sin dentista',
                ]),
            ],
            'pacientes' => [
                ['paciente', 'telefono', 'citas', 'tratamientos', 'ganancias'],
                $appointments->pluck('patient')->merge($treatments->pluck('patient'))->filter()->unique('id')->map(fn (Patient $patient) => [
                    'paciente' => $patient->nombre,
                    'telefono' => $patient->telefono ?: 'Sin telefono',
                    'citas' => $appointments->where('patient_id', $patient->id)->count(),
                    'tratamientos' => $treatments->where('patient_id', $patient->id)->count(),
                    'ganancias' => (float) $treatments->where('patient_id', $patient->id)->where('estado', 'Realizado')->sum('costo'),
                ])->values(),
            ],
            default => [
                ['fecha', 'paciente', 'tratamiento', 'tipo', 'estado', 'costo', 'dentista'],
                $treatments->map(fn (Treatment $treatment) => [
                    'fecha' => $treatment->fecha?->toDateString(),
                    'paciente' => $treatment->patient?->nombre ?? $treatment->paciente,
                    'tratamiento' => $treatment->tratamiento,
                    'tipo' => $treatment->tipo,
                    'estado' => $treatment->estado,
                    'costo' => (float) $treatment->costo,
                    'dentista' => $treatment->appointment?->dentist?->nombre ?? 'Sin dentista',
                ]),
            ],
        };

        $titles = [
            'ganancias' => 'Reporte de ganancias',
            'citas' => 'Reporte de citas',
            'pacientes' => 'Reporte de pacientes',
            'tratamientos' => 'Reporte de tratamientos',
        ];
        $labels = [
            'fecha' => 'Fecha', 'hora' => 'Hora', 'paciente' => 'Paciente', 'telefono' => 'Telefono',
            'tratamiento' => 'Tratamiento', 'tipo' => 'Tipo', 'estado' => 'Estado', 'dentista' => 'Dentista',
            'citas' => 'Citas', 'tratamientos' => 'Tratamientos', 'ganancia' => 'Ganancia',
            'ganancias' => 'Ganancias', 'costo' => 'Costo',
        ];

        return $this->downloadExcelReport(
            $titles[$reportType],
            $reportType,
            $headers,
            $labels,
            $rows,
            $from,
            $to,
            $request->session()->get('access_name', 'Usuario DentiFlow'),
            $totalEarnings
        );
    }

    private function downloadExcelReport(string $title, string $reportType, array $headers, array $labels, $rows, string $from, string $to, string $generatedBy, float $totalEarnings)
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()->setCreator('DentiFlow')->setTitle($title)->setSubject('Reporte clinico');
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(ucfirst($reportType));
        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));
        $sheet->mergeCells("A1:{$lastColumn}1");
        $sheet->setCellValue('A1', $title);
        $sheet->mergeCells("A2:{$lastColumn}2");
        $sheet->setCellValue('A2', 'Periodo: '.Carbon::parse($from)->format('d/m/Y').' al '.Carbon::parse($to)->format('d/m/Y'));
        $sheet->mergeCells("A3:{$lastColumn}3");
        $sheet->setCellValue('A3', 'Generado por '.$generatedBy.' el '.now()->format('d/m/Y H:i'));
        $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'font' => ['bold' => true, 'size' => 18, 'color' => ['RGB' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['RGB' => '9700CF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(30);
        $headerRow = 5;
        foreach ($headers as $index => $header) {
            $column = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValue($column.$headerRow, $labels[$header] ?? ucfirst($header));
        }
        $sheet->getStyle("A{$headerRow}:{$lastColumn}{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['RGB' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['RGB' => '512569']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['RGB' => 'EAD2FB']]],
        ]);
        $moneyColumns = ['ganancia', 'ganancias', 'costo'];
        $rowNumber = 6;
        foreach ($rows as $row) {
            foreach ($headers as $index => $header) {
                $column = Coordinate::stringFromColumnIndex($index + 1);
                $value = $row[$header] ?? '';
                if ($header === 'fecha' && $value) {
                    $sheet->setCellValue($column.$rowNumber, ExcelDate::PHPToExcel(Carbon::parse($value)));
                    $sheet->getStyle($column.$rowNumber)->getNumberFormat()->setFormatCode('dd/mm/yyyy');
                } elseif (in_array($header, array_merge($moneyColumns, ['citas', 'tratamientos']), true)) {
                    $sheet->setCellValueExplicit($column.$rowNumber, (float) $value, DataType::TYPE_NUMERIC);
                } else {
                    $sheet->setCellValueExplicit($column.$rowNumber, (string) $value, DataType::TYPE_STRING);
                }
                if (in_array($header, $moneyColumns, true)) {
                    $sheet->getStyle($column.$rowNumber)->getNumberFormat()->setFormatCode('"$"#,##0.00');
                }
            }
            $rowNumber++;
        }
        $lastDataRow = max(5, $rowNumber - 1);
        if ($rows->isNotEmpty()) {
            $sheet->getStyle("A6:{$lastColumn}{$lastDataRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E7DDEC');
            $sheet->getStyle("A6:{$lastColumn}{$lastDataRow}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
            $sheet->setAutoFilter("A5:{$lastColumn}{$lastDataRow}");
        }
        $totalRow = $rowNumber;
        $totalValueColumn = $lastColumn;
        $totalLabelColumn = Coordinate::stringFromColumnIndex(max(1, count($headers) - 1));
        $sheet->setCellValue($totalLabelColumn.$totalRow, 'GANANCIAS TOTALES');
        $sheet->setCellValueExplicit($totalValueColumn.$totalRow, $totalEarnings, DataType::TYPE_NUMERIC);
        $sheet->getStyle($totalLabelColumn.$totalRow.':'.$totalValueColumn.$totalRow)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['RGB' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['RGB' => '9700CF']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['RGB' => 'EAD2FB']]],
        ]);
        $sheet->getStyle($totalValueColumn.$totalRow)->getNumberFormat()->setFormatCode('"$"#,##0.00');
        $widths = ['fecha' => 14, 'hora' => 13, 'paciente' => 28, 'telefono' => 17, 'tratamiento' => 28, 'tipo' => 24, 'estado' => 17, 'dentista' => 26, 'citas' => 12, 'tratamientos' => 16, 'ganancia' => 16, 'ganancias' => 16, 'costo' => 16];
        foreach ($headers as $index => $header) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index + 1))->setWidth($widths[$header] ?? 18);
        }
        $sheet->freezePane('A6');
        $sheet->setSelectedCell('A1');
        $filename = 'reporte-'.$reportType.'-dentiflow-'.$from.'-'.$to.'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    private function resolvePeriod(
        string $periodo,
        ?string $desde,
        ?string $hasta,
        ?string $fecha = null,
        ?string $mes = null
    ): array
    {
        $today = Carbon::today();
        $from = $today->copy()->subMonths(5)->startOfMonth();
        $to = $today->copy()->endOfMonth();

        switch ($periodo) {
            case 'Hoy':
                $from = $today->copy();
                $to = $today->copy();
                break;
            case 'Dia especifico':
            case 'Día específico':
                $selectedDay = $this->parseReportDate($fecha);
                if (! $selectedDay) {
                    return [$from->toDateString(), $to->toDateString(), $periodo, false];
                }
                $from = $selectedDay->copy();
                $to = $selectedDay->copy();
                break;
            case 'Este mes':
                $from = $today->copy()->startOfMonth();
                $to = $today->copy()->endOfMonth();
                break;
            case 'Mes especifico':
            case 'Mes específico':
                $selectedMonth = $this->parseReportMonth($mes);
                if (! $selectedMonth) {
                    return [$from->toDateString(), $to->toDateString(), $periodo, false];
                }
                $from = $selectedMonth->copy()->startOfMonth();
                $to = $selectedMonth->copy()->endOfMonth();
                break;
            case 'Ultimos 3 meses':
            case 'Últimos 3 meses':
                $from = $today->copy()->subMonths(2)->startOfMonth();
                break;
            case 'Ultimos 6 meses':
            case 'Últimos 6 meses':
                $from = $today->copy()->subMonths(5)->startOfMonth();
                break;
            case 'Este ano':
            case 'Este año':
                $from = $today->copy()->startOfYear();
                break;
            case 'Personalizado':
                $customFrom = $this->parseReportBoundary($desde, false);
                $customTo = $this->parseReportBoundary($hasta, true);

                if ($customFrom && $customTo && $customFrom->lte($customTo)) {
                    $from = $customFrom;
                    $to = $customTo;
                } else {
                    return [$from->toDateString(), $to->toDateString(), $periodo, false];
                }
                break;
        }

        return [$from->toDateString(), $to->toDateString(), $periodo, true];
    }

    private function parseReportDate(?string $value): ?Carbon
    {
        if (! $value || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            $date = Carbon::createFromFormat('!Y-m-d', $value);
            return $date->format('Y-m-d') === $value ? $date : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseReportMonth(?string $value): ?Carbon
    {
        if (! $value || ! preg_match('/^\d{4}-\d{2}$/', $value)) {
            return null;
        }

        try {
            $month = Carbon::createFromFormat('!Y-m', $value);
            return $month->format('Y-m') === $value ? $month : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseReportBoundary(?string $value, bool $end): ?Carbon
    {
        if ($date = $this->parseReportDate($value)) {
            return $date;
        }

        if ($month = $this->parseReportMonth($value)) {
            return $end ? $month->endOfMonth() : $month->startOfMonth();
        }

        return null;
    }

    private function previousPeriodRange(string $from, string $to): array
    {
        $start = Carbon::createFromFormat('Y-m-d', $from);
        $end = Carbon::createFromFormat('Y-m-d', $to);
        $interval = $start->diffInDays($end) + 1;

        $previousEnd = $start->copy()->subDay();
        $previousStart = $previousEnd->copy()->subDays($interval - 1);

        return [$previousStart->toDateString(), $previousEnd->toDateString()];
    }

    private function buildTrend(int|float $current, int|float $previous, string $label): array
    {
        if ((float) $previous === 0.0) {
            return [
                'arrow' => $current >= 0 ? '▲' : '▼',
                'percent' => $current == 0 ? 0 : 100,
                'positive' => $current >= 0,
                'label' => $label,
            ];
        }

        $percent = round((($current - $previous) / max($previous, 1)) * 100);

        return [
            'arrow' => $current >= $previous ? '▲' : '▼',
            'percent' => abs($percent),
            'positive' => $current >= $previous,
            'label' => $label,
        ];
    }

    private function buildMonthlyEarnings(string $from, string $to, $treatments): array
    {
        $start = Carbon::createFromFormat('Y-m-d', $from)->startOfMonth();
        $end = Carbon::createFromFormat('Y-m-d', $to)->endOfMonth();
        $months = [];

        while ($start->lte($end)) {
            $months[$start->format('Y-m')] = [
                'mes' => $start->translatedFormat('M'),
                'valor' => 0,
            ];
            $start->addMonth();
        }

        foreach ($treatments as $treatment) {
            if ($treatment->estado !== 'Realizado' || ! $treatment->fecha) {
                continue;
            }

            $key = $treatment->fecha->format('Y-m');
            if (isset($months[$key])) {
                $months[$key]['valor'] += (float) $treatment->costo;
            }
        }

        return array_values($months);
    }

    private function sessionDentist(Request $request): ?Dentist
    {
        $user = User::find($request->session()->get('access_user_id'))
            ?? User::where('email', $request->session()->get('access_email'))->first();

        if (! $user) {
            return null;
        }

        return Dentist::where('user_id', $user->id)->first()
            ?? Dentist::whereNull('user_id')->where('nombre', $user->name)->first();
    }

    private function completeOverdueAppointments(Request $request): void
    {
        $isDoctor = $request->session()->get('access_role') === 'doctor';
        $doctorDentist = $isDoctor ? $this->sessionDentist($request) : null;

        app(AppointmentCompletionService::class)->completeOverdue($doctorDentist?->id);
    }

    private function scopeAppointmentsForRole($query, bool $isDoctor, ?Dentist $dentist)
    {
        if ($isDoctor) {
            $query->where('dentist_id', $dentist?->id ?? 0);
        }

        return $query;
    }

    private function scopeTreatmentsForRole($query, bool $isDoctor, ?Dentist $dentist)
    {
        if ($isDoctor) {
            $query->whereHas('appointment', fn ($appointmentQuery) => $appointmentQuery->where('dentist_id', $dentist?->id ?? 0));
        }

        return $query;
    }
}
