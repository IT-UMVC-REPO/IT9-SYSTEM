<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductUnitVariant;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StockManager
{
    public function decrementStock(ProductUnitVariant $variant, int $quantity): void
    {
        $this->changeVariantStock($variant, $quantity, decrement: true);
    }

    public function incrementStock(ProductUnitVariant $variant, int $quantity): void
    {
        $this->changeVariantStock($variant, $quantity, decrement: false);
    }

    public function setStock(ProductUnitVariant $variant, int $quantity): void
    {
        if ($quantity < 0) {
            throw new RuntimeException('Stock quantity cannot be negative.');
        }

        DB::transaction(function () use ($variant, $quantity): void {
            $lockedVariant = ProductUnitVariant::query()
                ->whereKey($variant->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $product = Product::query()
                ->whereKey($lockedVariant->product_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($this->usesCanonicalStock($product, $lockedVariant)) {
                $product->forceFill([
                    'canonical_stock_quantity' => $quantity * $lockedVariant->unit->conversionFactor(),
                ])->save();

                $this->syncVariantStocks($product);

                return;
            }

            $lockedVariant->forceFill(['stock_quantity' => $quantity])->save();

            if ($lockedVariant->is_default) {
                $product->forceFill(['stock_quantity' => $quantity])->save();
            }
        }, attempts: 5);
    }

    public function decrementProductStock(Product $product, int $quantity): void
    {
        $this->changeProductStock($product, $quantity, decrement: true);
    }

    public function incrementProductStock(Product $product, int $quantity): void
    {
        $this->changeProductStock($product, $quantity, decrement: false);
    }

    public function setProductStock(Product $product, int $quantity): void
    {
        if ($quantity < 0) {
            throw new RuntimeException('Stock quantity cannot be negative.');
        }

        DB::transaction(function () use ($product, $quantity): void {
            Product::query()
                ->whereKey($product->getKey())
                ->lockForUpdate()
                ->firstOrFail()
                ->forceFill(['stock_quantity' => $quantity])
                ->save();
        }, attempts: 5);
    }

    public function availableQuantityFor(ProductUnitVariant $variant): int
    {
        $product = $variant->relationLoaded('product')
            ? $variant->product
            : $variant->product()->firstOrFail();

        if ($this->usesCanonicalStock($product, $variant)) {
            return (int) floor(((float) $product->canonical_stock_quantity) / $variant->unit->conversionFactor());
        }

        return (int) $variant->stock_quantity;
    }

    public function isInStock(ProductUnitVariant $variant, int $requestedQuantity): bool
    {
        return $this->availableQuantityFor($variant) >= $requestedQuantity;
    }

    private function changeVariantStock(ProductUnitVariant $variant, int $quantity, bool $decrement): void
    {
        if ($quantity < 1) {
            return;
        }

        DB::transaction(function () use ($variant, $quantity, $decrement): void {
            $lockedVariant = ProductUnitVariant::query()
                ->whereKey($variant->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $product = Product::query()
                ->whereKey($lockedVariant->product_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($this->usesCanonicalStock($product, $lockedVariant)) {
                $delta = $quantity * $lockedVariant->unit->conversionFactor();
                $currentCanonical = (float) $product->canonical_stock_quantity;
                $nextCanonical = $decrement ? $currentCanonical - $delta : $currentCanonical + $delta;

                if ($nextCanonical < 0) {
                    throw new RuntimeException('Insufficient stock for this unit variant.');
                }

                $product->forceFill(['canonical_stock_quantity' => $nextCanonical])->save();
                $this->syncVariantStocks($product);

                return;
            }

            $nextQuantity = $decrement
                ? $lockedVariant->stock_quantity - $quantity
                : $lockedVariant->stock_quantity + $quantity;

            if ($nextQuantity < 0) {
                throw new RuntimeException('Insufficient stock for this unit variant.');
            }

            $lockedVariant->forceFill(['stock_quantity' => $nextQuantity])->save();

            if ($lockedVariant->is_default) {
                $product->forceFill(['stock_quantity' => $nextQuantity])->save();
            }
        }, attempts: 5);
    }

    private function changeProductStock(Product $product, int $quantity, bool $decrement): void
    {
        if ($quantity < 1) {
            return;
        }

        DB::transaction(function () use ($product, $quantity, $decrement): void {
            $lockedProduct = Product::query()
                ->whereKey($product->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $nextQuantity = $decrement
                ? $lockedProduct->stock_quantity - $quantity
                : $lockedProduct->stock_quantity + $quantity;

            if ($nextQuantity < 0) {
                throw new RuntimeException('Insufficient stock for this product.');
            }

            $lockedProduct->forceFill(['stock_quantity' => $nextQuantity])->save();
        }, attempts: 5);
    }

    private function usesCanonicalStock(Product $product, ProductUnitVariant $variant): bool
    {
        return $product->canonical_stock_unit !== null
            && $product->canonical_stock_quantity !== null
            && $variant->unit->conversionFactor() !== null
            && $product->canonical_stock_unit === $variant->unit->baseUnit();
    }

    private function syncVariantStocks(Product $product): void
    {
        $freshProduct = $product->fresh(['unitVariants']);

        if ($freshProduct === null || $freshProduct->canonical_stock_quantity === null) {
            return;
        }

        foreach ($freshProduct->unitVariants as $variant) {
            if ($variant->unit->conversionFactor() === null) {
                continue;
            }

            $variant->forceFill([
                'stock_quantity' => (int) floor(((float) $freshProduct->canonical_stock_quantity) / $variant->unit->conversionFactor()),
            ])->save();
        }

        $defaultVariant = $freshProduct->unitVariants->firstWhere('is_default', true);

        if ($defaultVariant !== null) {
            $freshProduct->forceFill([
                'stock_quantity' => (int) $defaultVariant->stock_quantity,
            ])->save();
        }
    }
}
