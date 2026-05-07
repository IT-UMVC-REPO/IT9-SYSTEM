<?php

use App\Enums\ProductUnit;

test('product unit formats abbreviations stock and prices', function (): void {
    expect(ProductUnit::Kilogram->abbreviation())->toBe('kg')
        ->and(ProductUnit::Kilogram->stockLabel(50))->toBe('50 kg')
        ->and(ProductUnit::Piece->stockLabel(1))->toBe('1 piece')
        ->and(ProductUnit::Piece->stockLabel(5))->toBe('5 pieces')
        ->and(ProductUnit::Kilogram->priceLabel(62))->toBe('₱62.00 / kg');
});

test('product unit category suggestions expose sensible defaults', function (): void {
    expect(ProductUnit::suggestionsForCategory('rice')[0])->toBe(ProductUnit::Kilogram)
        ->and(ProductUnit::suggestionsForCategory('eggs')[0])->toBe(ProductUnit::Tray);
});
