<?php

use App\Enums\ProductUnit;
use App\Models\Product;
use App\Models\ProductUnitVariant;
use App\Services\StockManager;
use App\Support\UnitFormatter;

test('a product without variants falls back to top level price unit stock and conversion', function (): void {
    $product = Product::factory()->active()->create([
        'price' => 75,
        'stock_quantity' => 7,
        'unit' => ProductUnit::Kilogram,
        'conversion_unit' => ProductUnit::Gram,
        'conversion_unit_quantity' => 1000,
    ]);

    $conversion = $product->conversionFor(2);

    expect($product->hasVariants())->toBeFalse()
        ->and($product->saleUnit())->toBe(ProductUnit::Kilogram)
        ->and($product->salePrice())->toBe(75.0)
        ->and($product->saleStockQuantity())->toBe(7)
        ->and($conversion?->toUnit)->toBe(ProductUnit::Gram)
        ->and($conversion?->toQuantity)->toBe(2000.0);
});

test('weight based variants deduct from canonical stock', function (): void {
    $product = Product::factory()->active()->create([
        'unit' => ProductUnit::Kilogram,
        'stock_quantity' => 25,
        'canonical_stock_unit' => ProductUnit::Gram,
        'canonical_stock_quantity' => 25000,
    ]);

    $kilo = ProductUnitVariant::factory()->default()->for($product)->create([
        'unit' => ProductUnit::Kilogram,
        'price' => 200,
        'stock_quantity' => 25,
    ]);
    $gram = ProductUnitVariant::factory()->for($product)->create([
        'unit' => ProductUnit::Gram,
        'price' => 0.25,
        'stock_quantity' => 25000,
        'sort_order' => 1,
    ]);

    app(StockManager::class)->decrementStock($kilo, 2);
    app(StockManager::class)->decrementStock($gram, 500);

    expect((float) $product->fresh()->canonical_stock_quantity)->toBe(22500.0)
        ->and($kilo->fresh()->stock_quantity)->toBe(22)
        ->and($gram->fresh()->stock_quantity)->toBe(22500)
        ->and($product->fresh()->stock_quantity)->toBe(22);
});

test('count based variants keep independent stock', function (): void {
    $product = Product::factory()->active()->create([
        'unit' => ProductUnit::Piece,
        'stock_quantity' => 30,
        'canonical_stock_unit' => null,
        'canonical_stock_quantity' => null,
    ]);

    $piece = ProductUnitVariant::factory()->default()->for($product)->create([
        'unit' => ProductUnit::Piece,
        'stock_quantity' => 30,
    ]);
    $tray = ProductUnitVariant::factory()->for($product)->create([
        'unit' => ProductUnit::Tray,
        'stock_quantity' => 2,
        'sort_order' => 1,
    ]);

    app(StockManager::class)->decrementStock($tray, 1);

    expect($tray->fresh()->stock_quantity)->toBe(1)
        ->and($piece->fresh()->stock_quantity)->toBe(30)
        ->and($product->fresh()->stock_quantity)->toBe(30);
});

test('unit formatter covers edge quantities for marketplace units', function (): void {
    expect(UnitFormatter::format(ProductUnit::Piece, 0))->toBe('0 pieces')
        ->and(UnitFormatter::format(ProductUnit::Piece, 1))->toBe('1 piece')
        ->and(UnitFormatter::format(ProductUnit::Dozen, 2))->toBe('2 dozen')
        ->and(UnitFormatter::format(ProductUnit::Kilogram, 1.25))->toBe('1.25 kg');
});
