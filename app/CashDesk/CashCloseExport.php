<?php

declare(strict_types=1);

namespace App\CashDesk;

use App\Street\Containers\Enums\ContainerMovementType;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The cash close the screen shows, as a file to print (PDF) or to work with in a spreadsheet (Excel).
 * Both take the figures from CashCloseReport, so the files never disagree with the screen.
 */
class CashCloseExport
{
    private const array ORIGINS = ['stop' => 'Parada', 'loose' => 'Sin visita'];

    private const array MOVEMENTS = [ContainerMovementType::Delivered->value => 'Entregado', ContainerMovementType::Returned->value => 'Devuelto'];

    /**
     * @param  array<string, mixed>  $settlement  as built by CashCloseReport::forDriver
     */
    public function pdf(string $driver, string $date, array $settlement): Response
    {
        return Pdf::loadView('cash-close.pdf', ['driver' => $driver, 'date' => $date, 'settlement' => $settlement])
            ->setPaper('a4')
            ->download($this->fileName($driver, $date, 'pdf'));
    }

    /**
     * @param  array<string, mixed>  $settlement  as built by CashCloseReport::forDriver
     */
    public function xlsx(string $driver, string $date, array $settlement): StreamedResponse
    {
        return response()->streamDownload(function () use ($driver, $date, $settlement): void {
            $writer = new Writer;
            $writer->openToFile('php://output');

            foreach ($this->sheets($driver, $date, $settlement) as $name => $rows) {
                if ($name !== 'Resumen') {
                    $writer->addNewSheetAndMakeItCurrent();
                }
                $writer->getCurrentSheet()->setName($name);
                foreach ($rows as $row) {
                    $writer->addRow(Row::fromValues($row));
                }
            }

            $writer->close();
        }, $this->fileName($driver, $date, 'xlsx'), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  array<string, mixed>  $settlement
     * @return array<string, array<int, array<int, mixed>>>
     */
    private function sheets(string $driver, string $date, array $settlement): array
    {
        $details = $settlement['details'];
        $payments = fn (array $rows): array => [
            ['Hora', 'Cliente', 'Concepto', 'Importe', 'Origen'],
            ...array_map(fn (array $row): array => [
                $this->hour($row['at']), $row['customer'], $row['concept'], $row['amount'], self::ORIGINS[$row['origin']],
            ], $rows),
        ];
        $remaining = collect($settlement['stockRemaining'])->pluck('quantity', 'product');
        $loaded = collect($settlement['stockLoaded'])->keyBy('product');
        $products = $loaded->keys()->merge($remaining->keys())->unique()->values();

        return [
            'Resumen' => [
                ['Repartidor', $driver],
                ['Fecha', Carbon::parse($date)->format('d/m/Y')],
                ['Estado de la ruta', $settlement['status']],
                [],
                ['Ventas totales', $settlement['totalSales']],
                ['Ventas fiadas', $settlement['creditSales']],
                ['Efectivo', $settlement['cashAmount']],
                ['Transferencias', $settlement['transferAmount']],
                ['Tarjeta', $settlement['cardAmount']],
                ['Total cobrado', $settlement['totalCollected']],
                ['Cobro de deudas', $settlement['debtCollected']],
                ['Envases entregados', $settlement['containersDelivered']],
                ['Envases devueltos', $settlement['containersReturned']],
                ['Ventas post-cierre', $settlement['postCloseSales']],
            ],
            'Efectivo' => $payments($details['cash']),
            'Transferencias' => $payments($details['transfer']),
            'Tarjeta' => $payments($details['card']),
            'Fiado' => [
                ['Hora', 'Cliente', 'Importe', 'Origen'],
                ...array_map(fn (array $row): array => [
                    $this->hour($row['at']), $row['customer'], $row['amount'], self::ORIGINS[$row['origin']],
                ], $details['credit']),
            ],
            'Post-cierre' => [
                ['Hora', 'Cliente', 'Importe', 'Tipo', 'Origen'],
                ...array_map(fn (array $row): array => [
                    $this->hour($row['at']), $row['customer'], $row['amount'], $row['credit'] ? 'Fiado' : 'Contado', self::ORIGINS[$row['origin']],
                ], $details['postClose']),
            ],
            'Envases' => [
                ['Hora', 'Cliente', 'Envase', 'Movimiento', 'Cantidad', 'Origen'],
                ...array_map(fn (array $row): array => [
                    $this->hour($row['at']), $row['customer'], $row['container'], self::MOVEMENTS[$row['movement']] ?? $row['movement'], $row['quantity'], self::ORIGINS[$row['origin']],
                ], $details['containers']),
            ],
            'Stock' => [
                ['Producto', 'Cargado', 'Cargas', 'Queda en el camión'],
                ...$products->map(fn (?string $product): array => [
                    $product,
                    $loaded->get($product)['quantity'] ?? 0,
                    $loaded->get($product)['loads'] ?? 0,
                    $remaining->get($product, 0),
                ])->all(),
            ],
        ];
    }

    /** The hour of a record; the day is in the header, so the "Hora" column carries only the hour. */
    private function hour(?string $at): string
    {
        return $at === null ? '' : Carbon::parse($at)->format('H:i');
    }

    private function fileName(string $driver, string $date, string $extension): string
    {
        return 'cierre-caja-'.Str::slug($driver).'-'.$date.'.'.$extension;
    }
}
