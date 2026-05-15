<?php

namespace App\Models;

use App\Enums\ProductUnit;
use App\Support\UnitConversionResult;
use App\Support\UnitFormatter;
use Database\Factories\ProductUnitVariantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'unit', 'price', 'stock_quantity', 'conversion_unit', 'conversion_unit_quantity', 'is_default', 'sort_order'])]
class ProductUnitVariant extends Model
{
    /** @use HasFactory<ProductUnitVariantFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit' => ProductUnit::class,
            'price' => 'decimal:2',
            'stock_quantity' => 'int',
            'conversion_unit' => ProductUnit::class,
            'conversion_unit_quantity' => 'float',
            'is_default' => 'boolean',
            'sort_order' => 'int',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function hasConversion(): bool
    {
        return $this->conversion_unit instanceof ProductUnit
            && $this->conversion_unit_quantity !== null
            && (float) $this->conversion_unit_quantity > 0;
    }

    public function canAutoConvert(): bool
    {
        return $this->conversion_unit instanceof ProductUnit
            && $this->unit->isConvertibleTo($this->conversion_unit);
    }

    public function conversionFor(float $quantity): ?UnitConversionResult
    {
        if (! $this->hasConversion()) {
            return null;
        }

        return new UnitConversionResult(
            fromUnit: $this->unit,
            fromQuantity: $quantity,
            toUnit: $this->conversion_unit,
            toQuantity: $quantity * (float) $this->conversion_unit_quantity,
            fromUnitPrice: (float) $this->price,
        );
    }

    public function priceWithUnit(): string
    {
        return UnitFormatter::pricePerUnit($this->unit, $this->price);
    }
}
