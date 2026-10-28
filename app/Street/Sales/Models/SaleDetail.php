<?php

namespace App\Street\Sales\Models;

use App\Catalog\Models\Product;
use App\Customers\Agreements\Models\CustomerAgreement;
use App\Street\Sales\Database\Factories\SaleDetailFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['sale_id', 'product_id', 'customer_agreement_id', 'quantity', 'unit_price'])]
class SaleDetail extends Model
{
    /** @use HasFactory<SaleDetailFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function agreement(): BelongsTo
    {
        return $this->belongsTo(CustomerAgreement::class, 'customer_agreement_id');
    }
}
