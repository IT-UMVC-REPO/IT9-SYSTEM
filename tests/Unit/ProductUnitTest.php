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

test('countable selling units expose piece based conversion options', function (): void {
    expect(ProductUnit::Dozen->stockLabel(1))->toBe('1 dozen')
        ->and(ProductUnit::Dozen->stockLabel(3))->toBe('3 dozens')
        ->and(ProductUnit::Dozen->suggestedBaseUnits())->toBe([
            ['value' => 'piece', 'label' => 'Pieces'],
        ])
        ->and(ProductUnit::Bilao->suggestedBaseUnits())->toBe([])
        ->and(ProductUnit::Pack->suggestedBaseUnits()[0])->toBe(['value' => 'piece', 'label' => 'Pieces'])
        ->and(ProductUnit::Dozen->conversionLabel(12, 'piece'))->toBe('1 dozen = 12 pieces');
});
