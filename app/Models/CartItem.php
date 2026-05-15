<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['cart_id', 'product_id', 'product_unit_variant_id', 'quantity'])]
class CartItem extends Model
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
        ];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unitVariant(): BelongsTo
    {
        return $this->belongsTo(ProductUnitVariant::class, 'product_unit_variant_id');
    }

    public function unitPrice(): float
    {
        return (float) ($this->unitVariant?->price ?? $this->product->price);
    }

    public function lineTotal(): float
    {
        return $this->unitPrice() * $this->quantity;
    }
}
