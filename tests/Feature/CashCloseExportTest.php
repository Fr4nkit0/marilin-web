<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Catalog\Models\ContainerType;
use App\Catalog\Models\PaymentMethod;
use App\Catalog\Models\Product;
use App\Customers\Customers\Models\Customer;
use App\Models\ContainerMovement;
use App\Models\Payment;
use App\Models\RouteInventory;
use App\Models\Sale;
use App\Routes\Routes\Models\Route;
use App\Services\CashClose\CashCloseReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use OpenSpout\Reader\XLSX\Reader;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo('2026-10-20 18:00:00');
    $this->actingAs(User::factory()->superUser()->create());

    $this->driverUser = User::factory()->create(['name' => 'Juan Pérez']);
    $this->driver = Driver::factory()->create(['user_id' => $this->driverUser->id, 'enabled' => true]);
    $this->route = Route::factory()->create(['assigned_driver_id' => $this->driver->id, 'date' => '2026-10-20', 'status' => 'IN_PROGRESS']);
    $cash = PaymentMethod::factory()->create(['code' => 'CASH', 'name' => 'Efectivo']);
    $transfer = PaymentMethod::factory()->create(['code' => 'TRANSFER', 'name' => 'Transferencia']);
    $bidon = ContainerType::factory()->create(['code' => 'BIDON_20L', 'description' => 'Bidón 20L']);
    $hotel = Customer::factory()->create(['name' => 'Hotel Altos de la Viña', 'driver_id' => $this->driver->id]);
    $product = Product::factory()->create(['name' => 'Bidón 20L']);

    $record = ['created_by' => $this->driverUser->id, 'customer_id' => $hotel->id, 'visit_id' => null];
    Sale::factory()->create([...$record, 'date' => '2026-10-20 10:00:00', 'status' => 'confirmed', 'total' => 9500, 'is_credit_sale' => false]);
    Sale::factory()->create([...$record, 'date' => '2026-10-20 10:30:00', 'status' => 'confirmed', 'total' => 2000, 'is_credit_sale' => true]);
    $paid = ['received_by' => $this->driverUser->id, 'customer_id' => $hotel->id, 'visit_id' => null, 'sale_id' => null, 'status' => 'CONFIRMED', 'paid_at' => '2026-10-20 11:00:00'];
    Payment::factory()->create([...$paid, 'payment_method_id' => $cash->id, 'amount' => 6500]);
    Payment::factory()->create([...$paid, 'payment_method_id' => $transfer->id, 'amount' => 3000]);
    ContainerMovement::factory()->create([...$record, 'container_type_id' => $bidon->id, 'movement_type' => 'DELIVERED', 'quantity' => 4]);
    RouteInventory::factory()->create(['route_id' => $this->route->id, 'product_id' => $product->id, 'current_quantity' => 7]);
});

function exportCashClose(string $format, ?int $driverId = null, string $date = '2026-10-20'): TestResponse
{
    return test()->get(route('cash-close.export', [
        'format' => $format,
        'repartidor_id' => $driverId ?? test()->driver->id,
        'date' => $date,
    ]));
}

/**
 * Every sheet of the downloaded workbook as rows of plain cell values, keyed by sheet name.
 *
 * @return array<string, array<int, array<int, mixed>>>
 */
function workbookRows(TestResponse $response): array
{
    $path = tempnam(sys_get_temp_dir(), 'cash-close').'.xlsx';
    file_put_contents($path, $response->streamedContent());

    $reader = new Reader;
    $reader->open($path);
    $sheets = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $sheets[$sheet->getName()][] = $row->toArray();
        }
    }
    $reader->close();
    unlink($path);

    return $sheets;
}

test('the cash close downloads as a PDF named after the driver and the day', function (): void {
    $response = exportCashClose('pdf');

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($response->headers->get('Content-Disposition'))->toContain('attachment')->toContain('cierre-caja-juan-perez-2026-10-20.pdf')
        ->and(str_starts_with((string) $response->getContent(), '%PDF'))->toBeTrue();
});

test('the PDF shows the same figures and records as the screen', function (): void {
    $settlement = app(CashCloseReport::class)->forDriver($this->driver, '2026-10-20');

    $html = view('cash-close.pdf', ['driver' => 'Juan Pérez', 'date' => '2026-10-20', 'settlement' => $settlement])->render();

    expect($html)
        ->toContain('Juan Pérez')
        ->toContain('2026-10-20')
        ->toContain('$ 11.500,00') // total sales
        ->toContain('$ 2.000,00') // credit
        ->toContain('$ 6.500,00') // cash
        ->toContain('$ 3.000,00') // transfer
        ->toContain('$ 9.500,00') // total collected and debt collected
        ->toContain('Hotel Altos de la Viña')
        ->toContain('Bidón 20L')
        ->toContain('Cobro de deuda');
});

test('the cash close downloads as an Excel workbook with the summary and one sheet per detail', function (): void {
    $response = exportCashClose('xlsx');

    $response->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    expect($response->headers->get('Content-Disposition'))->toContain('cierre-caja-juan-perez-2026-10-20.xlsx');

    $sheets = workbookRows($response);

    expect(array_keys($sheets))->toBe(['Resumen', 'Efectivo', 'Transferencias', 'Fiado', 'Envases', 'Stock'])
        ->and($sheets['Resumen'])->toContain(['Repartidor', 'Juan Pérez'])
        ->and($sheets['Resumen'])->toContain(['Fecha', '2026-10-20'])
        ->and($sheets['Resumen'])->toContain(['Ventas totales', 11500])
        ->and($sheets['Resumen'])->toContain(['Ventas fiadas', 2000])
        ->and($sheets['Resumen'])->toContain(['Efectivo', 6500])
        ->and($sheets['Resumen'])->toContain(['Transferencias', 3000])
        ->and($sheets['Resumen'])->toContain(['Total cobrado', 9500])
        ->and($sheets['Resumen'])->toContain(['Cobro de deudas', 9500])
        ->and($sheets['Resumen'])->toContain(['Envases entregados', 4])
        ->and($sheets['Resumen'])->toContain(['Envases devueltos', 0])
        ->and($sheets['Efectivo'])->toBe([
            ['Hora', 'Cliente', 'Concepto', 'Importe', 'Origen'],
            ['2026-10-20 11:00', 'Hotel Altos de la Viña', 'Cobro de deuda', 6500, 'Sin visita'],
        ])
        ->and($sheets['Fiado'][1])->toBe(['2026-10-20 10:30', 'Hotel Altos de la Viña', 2000, 'Sin visita'])
        ->and($sheets['Envases'][1])->toBe(['2026-10-20 18:00', 'Hotel Altos de la Viña', 'Bidón 20L', 'Entregado', 4, 'Sin visita'])
        ->and($sheets['Stock'])->toBe([
            ['Producto', 'Cargado', 'Cargas', 'Queda en el camión'],
            ['Bidón 20L', 0, 0, 7],
        ]);
});

test('exporting a day without a route answers not found', function (string $format): void {
    exportCashClose($format, date: '2026-10-19')->assertNotFound();
})->with(['pdf', 'xlsx']);

test('exporting an unknown driver answers not found', function (): void {
    exportCashClose('pdf', driverId: 999_999)->assertNotFound();
});

test('an unknown format is not a route', function (): void {
    $this->get('/cash-close/export/csv?repartidor_id='.$this->driver->id.'&date=2026-10-20')->assertNotFound();
});

test('guests cannot export the cash close', function (): void {
    auth()->logout();

    exportCashClose('pdf')->assertRedirect(route('login'));
});
