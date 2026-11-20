<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class InventoryBackendTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_item_can_be_created_with_valid_data(): void
    {
        $response = $this->withSession(['access_role' => 'asistente'])->post(route('inventario.store'), [
            'material' => 'Algodon dental',
            'stock' => 25,
            'reposicion' => 10,
            'costo' => 120.50,
            'proveedor' => 'Proveedor Dental',
        ]);

        $response->assertRedirect(route('inventario'));
        $this->assertDatabaseHas('inventory_items', [
            'material' => 'Algodon dental',
            'stock' => 25,
            'reposicion' => 10,
        ]);
    }

    public function test_inventory_item_without_provider_is_saved_as_without_provider(): void
    {
        $response = $this->withSession(['access_role' => 'asistente'])->post(route('inventario.store'), [
            'material' => 'Cubrebocas sin proveedor',
            'stock' => 20,
            'reposicion' => 5,
            'costo' => 80,
            'proveedor' => '',
        ]);

        $response->assertRedirect(route('inventario'));
        $this->assertDatabaseHas('inventory_items', [
            'material' => 'Cubrebocas sin proveedor',
            'proveedor' => 'Sin proveedor',
        ]);

        $this->withSession(['access_role' => 'asistente'])
            ->get(route('inventario'))
            ->assertOk()
            ->assertSee('Cubrebocas sin proveedor')
            ->assertSee('Sin proveedor');
    }

    public function test_inventory_material_must_be_unique(): void
    {
        InventoryItem::create([
            'material' => 'Anestesia',
            'stock' => 10,
            'reposicion' => 5,
            'costo' => 200,
            'proveedor' => 'Proveedor Dental',
        ]);

        $response = $this->withSession(['access_role' => 'asistente'])->post(route('inventario.store'), [
            'material' => 'Anestesia',
            'stock' => 20,
            'reposicion' => 5,
            'costo' => 220,
            'proveedor' => 'Otro proveedor',
        ]);

        $response->assertSessionHasErrors('material');
    }

    public function test_inventory_item_can_be_updated_without_failing_own_name(): void
    {
        $item = InventoryItem::create([
            'material' => 'Resina',
            'stock' => 8,
            'reposicion' => 4,
            'costo' => 500,
            'proveedor' => 'Proveedor Dental',
        ]);

        $response = $this->withSession(['access_role' => 'asistente'])->post(route('inventario.update', $item->id), [
            'material' => 'Resina',
            'stock' => 12,
            'reposicion' => 4,
            'costo' => 510,
            'proveedor' => 'Proveedor Dental',
        ]);

        $response->assertRedirect(route('inventario'));
        $this->assertDatabaseHas('inventory_items', ['id' => $item->id, 'stock' => 12]);
    }

    public function test_inventory_item_provider_can_be_cleared_to_without_provider(): void
    {
        $item = InventoryItem::create([
            'material' => 'Batas',
            'stock' => 8,
            'reposicion' => 4,
            'costo' => 150,
            'proveedor' => 'Proveedor Dental',
        ]);

        $this->withSession(['access_role' => 'asistente'])->post(route('inventario.update', $item->id), [
            'material' => 'Batas',
            'stock' => 8,
            'reposicion' => 4,
            'costo' => 150,
            'proveedor' => '',
        ])->assertRedirect(route('inventario'));

        $this->assertDatabaseHas('inventory_items', [
            'id' => $item->id,
            'proveedor' => 'Sin proveedor',
        ]);
    }

    public function test_inventory_index_calculates_restock_alerts(): void
    {
        InventoryItem::create([
            'material' => 'Guantes',
            'stock' => 3,
            'reposicion' => 10,
            'costo' => 100,
            'proveedor' => 'Proveedor Dental',
        ]);

        $response = $this->withSession(['access_role' => 'asistente'])->get(route('inventario'));

        $response->assertOk();
        $response->assertSee('Productos en alerta');
        $response->assertSee('Guantes');
        $this->assertTrue(
            InventoryItem::where('material', 'Guantes')->firstOrFail()->needsRestock()
        );
    }

    public function test_stock_status_uses_live_quantity_thresholds(): void
    {
        $item = new InventoryItem(['stock' => 12]);
        $this->assertSame('Stock alto', $item->stockStatusLabel());

        $item->stock = 8;
        $this->assertSame('Stock disponible', $item->stockStatusLabel());

        $item->stock = 5;
        $this->assertSame('Por agotarse', $item->stockStatusLabel());
        $this->assertTrue($item->needsRestock());

        $item->stock = 2;
        $this->assertSame('Stock bajo', $item->stockStatusLabel());
        $this->assertTrue($item->needsRestock());
    }

    public function test_assistant_bell_shows_low_and_running_out_stock_notifications(): void
    {
        InventoryItem::create([
            'material' => 'Anestesia critica',
            'stock' => 2,
            'reposicion' => 10,
            'costo' => 100,
            'proveedor' => 'Proveedor Dental',
        ]);
        InventoryItem::create([
            'material' => 'Resina por agotarse',
            'stock' => 5,
            'reposicion' => 10,
            'costo' => 100,
            'proveedor' => 'Proveedor Dental',
        ]);
        InventoryItem::create([
            'material' => 'Guantes disponibles',
            'stock' => 10,
            'reposicion' => 20,
            'costo' => 100,
            'proveedor' => 'Proveedor Dental',
        ]);

        $this->withSession(['access_role' => 'asistente'])
            ->get(route('inventario'))
            ->assertOk()
            ->assertSee('Stock bajo: Anestesia critica')
            ->assertSee('Por agotarse: Resina por agotarse')
            ->assertDontSee('Stock bajo: Guantes disponibles')
            ->assertDontSee('Por agotarse: Guantes disponibles');
    }

    public function test_inventory_provider_costs_leave_missing_provider_name_blank(): void
    {
        InventoryItem::create([
            'material' => 'Gasas',
            'stock' => 10,
            'reposicion' => 5,
            'costo' => 30,
            'proveedor' => 'Sin proveedor',
        ]);

        $this->withSession(['access_role' => 'asistente'])
            ->get(route('inventario'))
            ->assertOk()
            ->assertSee('<p class="font-semibold text-gray-800"></p>', false);
    }

    public function test_inventory_page_offers_pdf_and_excel_report_types(): void
    {
        $this->withSession(['access_role' => 'admin'])
            ->get(route('inventario'))
            ->assertOk()
            ->assertSee('Exportar inventario')
            ->assertSee('Inventario completo')
            ->assertSee('Productos por reponer')
            ->assertSee('Costos por proveedor')
            ->assertSee('Valor del inventario')
            ->assertSee(route('inventario.export.pdf', ['tipo' => 'completo']), false)
            ->assertSee(route('inventario.export.excel', ['tipo' => 'valor']), false);
    }

    public function test_inventory_report_downloads_a_real_pdf(): void
    {
        InventoryItem::create([
            'material' => 'Anestesia reporte PDF',
            'stock' => 4,
            'reposicion' => 10,
            'costo' => 250,
            'proveedor' => 'OdontoMed',
        ]);

        $response = $this->withSession(['access_role' => 'admin', 'access_name' => 'Administrador Inventario'])
            ->get(route('inventario.export.pdf', ['tipo' => 'alertas']));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString('inventario-alertas-dentiflow-', $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_inventory_report_downloads_a_formatted_excel_workbook(): void
    {
        InventoryItem::create([
            'material' => 'Resina reporte Excel',
            'stock' => 8,
            'reposicion' => 5,
            'costo' => 400,
            'proveedor' => 'Dental Excel',
        ]);

        $response = $this->withSession(['access_role' => 'admin', 'access_name' => 'Administrador Inventario'])
            ->get(route('inventario.export.excel', ['tipo' => 'valor']));

        $response->assertOk();
        $this->assertStringContainsString('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('content-type'));
        $this->assertStringContainsString('inventario-valor-dentiflow-', $response->headers->get('content-disposition'));

        $temporaryFile = tempnam(sys_get_temp_dir(), 'inventario-excel-');
        file_put_contents($temporaryFile, $response->streamedContent());
        $workbook = IOFactory::load($temporaryFile);
        $sheet = $workbook->getActiveSheet();

        $this->assertSame('Reporte de valor del inventario', $sheet->getCell('A1')->getValue());
        $this->assertSame('Material', $sheet->getCell('A5')->getValue());
        $materialRow = null;
        for ($row = 6; $row <= $sheet->getHighestDataRow(); $row++) {
            if ($sheet->getCell('A'.$row)->getValue() === 'Resina reporte Excel') {
                $materialRow = $row;
                break;
            }
        }
        $this->assertNotNull($materialRow);
        $this->assertSame(3200.0, (float) $sheet->getCell('D'.$materialRow)->getValue());
        $this->assertTrue($sheet->getAutoFilter()->getRange() !== '');

        $workbook->disconnectWorksheets();
        @unlink($temporaryFile);
    }
}
