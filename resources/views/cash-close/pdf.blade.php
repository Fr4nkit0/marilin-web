@php
    $money = fn ($amount): string => '$ '.number_format((float) $amount, 2, ',', '.');
    $day = \Illuminate\Support\Carbon::parse($date)->format('d/m/Y');
    $hour = fn (?string $at): string => $at === null ? '' : \Illuminate\Support\Carbon::parse($at)->format('H:i');
    $origins = ['stop' => 'Parada', 'loose' => 'Sin visita'];
    $movements = ['DELIVERED' => 'Entregado', 'RETURNED' => 'Devuelto'];
    $details = $settlement['details'];
    $remaining = collect($settlement['stockRemaining'])->pluck('quantity', 'product');
    $loaded = collect($settlement['stockLoaded'])->keyBy('product');
    $products = $loaded->keys()->merge($remaining->keys())->unique();
    $paymentSections = ['Efectivo' => $details['cash'], 'Transferencias' => $details['transfer'], 'Tarjeta' => $details['card']];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Cierre de caja {{ $driver }} {{ $day }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #0f172a; }
        h1 { font-size: 16px; margin: 0 0 4px; }
        h2 { font-size: 12px; margin: 18px 0 6px; border-bottom: 1px solid #cbd5e1; padding-bottom: 3px; }
        .meta { color: #475569; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 4px 6px; border-bottom: 1px solid #e2e8f0; text-align: left; }
        th { background: #f1f5f9; font-weight: bold; }
        .num { text-align: right; }
        .summary td:first-child { width: 60%; }
        .total td { font-weight: bold; background: #f8fafc; }
        .empty { color: #64748b; font-style: italic; }
        .signatures { margin-top: 40px; width: 100%; }
        .signatures td { border: 0; padding-top: 30px; text-align: center; }
        .line { border-top: 1px solid #0f172a; display: inline-block; width: 70%; padding-top: 3px; }
    </style>
</head>
<body>
    <h1>Cierre de caja y arqueo de ruta</h1>
    <div class="meta">Repartidor: <strong>{{ $driver }}</strong> &middot; Fecha: <strong>{{ $day }}</strong> &middot; Estado de la ruta: {{ $settlement['status'] }}</div>

    <h2>Resumen</h2>
    <table class="summary">
        <tr><td>Ventas totales</td><td class="num">{{ $money($settlement['totalSales']) }}</td></tr>
        <tr><td>Ventas fiadas (cuenta corriente)</td><td class="num">{{ $money($settlement['creditSales']) }}</td></tr>
        <tr><td>Cobro de deudas</td><td class="num">{{ $money($settlement['debtCollected']) }}</td></tr>
        <tr><td>Transferencias</td><td class="num">{{ $money($settlement['transferAmount']) }}</td></tr>
        <tr><td>Tarjeta</td><td class="num">{{ $money($settlement['cardAmount']) }}</td></tr>
        <tr><td>Total cobrado</td><td class="num">{{ $money($settlement['totalCollected']) }}</td></tr>
        <tr class="total"><td>Efectivo a entregar</td><td class="num">{{ $money($settlement['cashAmount']) }}</td></tr>
        <tr><td>Ventas post-cierre</td><td class="num">{{ $money($settlement['postCloseSales']) }}</td></tr>
        <tr><td>Envases entregados / devueltos</td><td class="num">{{ $settlement['containersDelivered'] }} / {{ $settlement['containersReturned'] }}</td></tr>
    </table>

    @foreach ($paymentSections as $title => $rows)
        <h2>{{ $title }}</h2>
        @if (count($rows) === 0)
            <p class="empty">Sin registros.</p>
        @else
            <table>
                <tr><th>Hora</th><th>Cliente</th><th>Concepto</th><th>Origen</th><th class="num">Importe</th></tr>
                @foreach ($rows as $row)
                    <tr><td>{{ $hour($row['at']) }}</td><td>{{ $row['customer'] }}</td><td>{{ $row['concept'] }}</td><td>{{ $origins[$row['origin']] }}</td><td class="num">{{ $money($row['amount']) }}</td></tr>
                @endforeach
            </table>
        @endif
    @endforeach

    <h2>Fiado</h2>
    @if (count($details['credit']) === 0)
        <p class="empty">Sin registros.</p>
    @else
        <table>
            <tr><th>Hora</th><th>Cliente</th><th>Origen</th><th class="num">Importe</th></tr>
            @foreach ($details['credit'] as $row)
                <tr><td>{{ $hour($row['at']) }}</td><td>{{ $row['customer'] }}</td><td>{{ $origins[$row['origin']] }}</td><td class="num">{{ $money($row['amount']) }}</td></tr>
            @endforeach
        </table>
    @endif

    <h2>Post-cierre</h2>
    @if (count($details['postClose']) === 0)
        <p class="empty">Sin registros.</p>
    @else
        <table>
            <tr><th>Hora</th><th>Cliente</th><th>Tipo</th><th>Origen</th><th class="num">Importe</th></tr>
            @foreach ($details['postClose'] as $row)
                <tr><td>{{ $hour($row['at']) }}</td><td>{{ $row['customer'] }}</td><td>{{ $row['credit'] ? 'Fiado' : 'Contado' }}</td><td>{{ $origins[$row['origin']] }}</td><td class="num">{{ $money($row['amount']) }}</td></tr>
            @endforeach
        </table>
    @endif

    <h2>Envases</h2>
    @if (count($details['containers']) === 0)
        <p class="empty">Sin registros.</p>
    @else
        <table>
            <tr><th>Hora</th><th>Cliente</th><th>Envase</th><th>Movimiento</th><th>Origen</th><th class="num">Cantidad</th></tr>
            @foreach ($details['containers'] as $row)
                <tr><td>{{ $hour($row['at']) }}</td><td>{{ $row['customer'] }}</td><td>{{ $row['container'] }}</td><td>{{ $movements[$row['movement']] ?? $row['movement'] }}</td><td>{{ $origins[$row['origin']] }}</td><td class="num">{{ $row['quantity'] }}</td></tr>
            @endforeach
        </table>
    @endif

    <h2>Stock del camión</h2>
    @if ($products->isEmpty())
        <p class="empty">Sin registros de inventario.</p>
    @else
        <table>
            <tr><th>Producto</th><th class="num">Cargado</th><th class="num">Cargas</th><th class="num">Queda en el camión</th></tr>
            @foreach ($products as $product)
                <tr><td>{{ $product }}</td><td class="num">{{ $loaded->get($product)['quantity'] ?? 0 }}</td><td class="num">{{ $loaded->get($product)['loads'] ?? 0 }}</td><td class="num">{{ $remaining->get($product, 0) }}</td></tr>
            @endforeach
        </table>
    @endif

    <table class="signatures">
        <tr><td><span class="line">Firma del repartidor</span></td><td><span class="line">Firma de administración</span></td></tr>
    </table>
</body>
</html>
