<?php

use App\Enums\ProductUnit;
use App\Enums\UnitType;
use App\Support\CategoryUnitSuggestion;
use App\Support\UnitFormatter;

test('product units expose type and conversion metadata', function (): void {
    expect(ProductUnit::Kilogram->type())->toBe(UnitType::Weight->value)
        ->and(ProductUnit::Kilogram->baseUnit())->toBe(ProductUnit::Gram)
        ->and(ProductUnit::Kilogram->conversionFactor())->toBe(1000.0)
        ->and(ProductUnit::Pound->conversionFactor())->toBe(453.592)
        ->and(ProductUnit::Liter->type())->toBe(UnitType::Volume->value)
        ->and(ProductUnit::Liter->baseUnit())->toBe(ProductUnit::Milliliter)
        ->and(ProductUnit::Dozen->type())->toBe(UnitType::Count->value)
        ->and(ProductUnit::Dozen->conversionFactor())->toBeNull()
        ->and(ProductUnit::Dozen->countFactor())->toBe(12)
        ->and(ProductUnit::Pair->countFactor())->toBe(2)
        ->and(ProductUnit::Milliliter->symbol())->toBe('mL');
});

test('product units convert compatible measurements', function (): void {
    expect(ProductUnit::Kilogram->convert(2, ProductUnit::Gram))->toBe(2000.0)
        ->and(ProductUnit::Gram->convert(500, ProductUnit::Kilogram))->toBe(0.5)
        ->and(round(ProductUnit::Pound->convert(1, ProductUnit::Ounce), 4))->toBe(16.0)
        ->and(ProductUnit::Liter->convert(1.5, ProductUnit::Milliliter))->toBe(1500.0);
});

test('product units reject incompatible conversions', function (): void {
    ProductUnit::Kilogram->convert(1, ProductUnit::Liter);
})->throws(InvalidArgumentException::class);

test('unit formatter handles count weight volume and fractional quantities', function (): void {
    expect(UnitFormatter::format(ProductUnit::Piece, 0))->toBe('0 pieces')
        ->and(UnitFormatter::format(ProductUnit::Piece, 1))->toBe('1 piece')
        ->and(UnitFormatter::format(ProductUnit::Piece, 1.5))->toBe('1.5 pieces')
        ->and(UnitFormatter::format(ProductUnit::Kilogram, 50))->toBe('50 kg')
        ->and(UnitFormatter::format(ProductUnit::Milliliter, 250))->toBe('250 mL')
        ->and(UnitFormatter::pricePerUnit(ProductUnit::Kilogram, 62))->toContain('/ kg');
});

test('category unit suggestions are no longer coupled to the enum', function (): void {
    expect(CategoryUnitSuggestion::forCategory('rice')[0])->toBe(ProductUnit::Kilogram)
        ->and(CategoryUnitSuggestion::forCategory('eggs')[0])->toBe(ProductUnit::Tray);
});
