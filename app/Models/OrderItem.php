<?php

namespace App\Models;

use App\Enums\ProductUnit;
use App\Support\UnitFormatter;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_id', 'product_id', 'product_unit_variant_id', 'quantity', 'unit_price', 'unit'])]
class OrderItem extends Model
{
    public $timestamps = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'product_unit_variant_id' => 'int',
            'quantity' => 'int',
            'unit_price' => 'decimal:2',
            'unit' => ProductUnit::class,
        ];
    }

    public function lineTotal(): string
    {
        $unitPrice = $this->unitVariant?->price ?? $this->unit_price;

        return UnitFormatter::currency((float) $unitPrice * $this->quantity);
    }

    public function quantityLabel(): string
    {
        return UnitFormatter::format($this->unit, $this->quantity);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unitVariant(): BelongsTo
    {
        return $this->belongsTo(ProductUnitVariant::class, 'product_unit_variant_id');
    }
}
