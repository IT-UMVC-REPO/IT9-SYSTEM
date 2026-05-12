<?php

namespace App\Models;

use App\Enums\ProductUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_id', 'product_id', 'quantity', 'unit_price', 'unit'])]
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
            'quantity' => 'int',
            'unit_price' => 'decimal:2',
            'unit' => ProductUnit::class,
        ];
    }

    public function lineTotal(): string
    {
        return '₱'.number_format((float) $this->unit_price * $this->quantity, 2);
    }

    public function quantityLabel(): string
    {
        return $this->unit->stockLabel($this->quantity);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
