---
name: actions-and-services
description: >
  Guidelines for single-responsibility Action classes and Services in Laravel.
---

# Action & Service Pattern in Laravel

## 1. Action Classes (`app/Actions/`)
- Cada Action debe tener **una sola responsabilidad** y un método ejecutable principal `execute()` o `__invoke()`.
- Se encargan de encapsular lógica de negocio compleja y transacciones de base de datos.

### Ejemplo:
```php
namespace App\Actions\Sales;

use App\Models\Sale;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;

class CreateSaleAction
{
    /**
     * Registra una venta, descuenta stock de ruta y actualiza el saldo del cliente.
     */
    public function execute(Customer $customer, array $saleData, array $items): Sale
    {
        return DB::transaction(function () use ($customer, $saleData, $items) {
            $sale = $customer->sales()->create($saleData);

            foreach ($items as $item) {
                $sale->details()->create($item);
                // Lógica de descuento de stock...
            }

            return $sale->load('details.product');
        });
    }
}
```

## 2. Inyección en Controladores
Los controladores reciben el Action vía inyección de dependencias o lo instancian directamente:

```php
public function store(StoreSaleRequest $request, CreateSaleAction $createSaleAction)
{
    $sale = $createSaleAction->execute(
        $request->customer(),
        $request->validated(),
        $request->validated('items')
    );

    return redirect()->route('sales.show', $sale)->with('success', 'Venta registrada con éxito');
}
```
