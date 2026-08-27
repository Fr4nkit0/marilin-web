---
name: pest-testing-standards
description: >
  Strict testing conventions with Pest PHP for unit, feature, and transaction tests.
---

# Pest Testing Standards

## 1. Estructura de Tests
- Usar nombres descriptivos en español o inglés (`it('creates sale and updates balance', ...)`).
- Usar `RefreshDatabase` para tests de integración.
- Estructura Arrange-Act-Assert (AAA).

```php
use App\Models\Customer;
use App\Models\Product;
use App\Actions\Sales\CreateSaleAction;

test('registra una venta y genera los detalles correctamente', function () {
    // Arrange (Preparar datos)
    $customer = Customer::factory()->create();
    $product = Product::factory()->create(['unit_price' => 1500]);
    $action = app(CreateSaleAction::class);

    // Act (Ejecutar acción)
    $sale = $action->execute($customer, ['total' => 3000], [
        ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 1500]
    ]);

    // Assert (Verificar resultados)
    expect($sale)->not->toBeNull();
    expect($sale->details)->toHaveCount(1);
    expect($sale->total)->toEqual('3000.00');
});
```
