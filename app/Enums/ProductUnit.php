<?php

namespace App\Enums;

enum ProductUnit: string
{
    case Kilogram = 'kg';
    case Gram = 'g';
    case Liter = 'L';
    case Milliliter = 'ml';
    case Piece = 'piece';
    case Dozen = 'dozen';
    case Pair = 'pair';
    case Bundle = 'bundle';
    case Pack = 'pack';
    case Bag = 'bag';
    case Tray = 'tray';
    case Bottle = 'bottle';
    case Can = 'can';
    case Box = 'box';
    case Sack = 'sack';
    case Bilao = 'bilao';

    public function label(): string
    {
        return match ($this) {
            self::Kilogram => 'Kilogram',
            self::Gram => 'Gram',
            self::Liter => 'Liter',
            self::Milliliter => 'Milliliter',
            self::Piece => 'Piece',
            self::Dozen => 'Dozen',
            self::Pair => 'Pair',
            self::Bundle => 'Bundle',
            self::Pack => 'Pack',
            self::Bag => 'Bag',
            self::Tray => 'Tray',
            self::Bottle => 'Bottle',
            self::Can => 'Can',
            self::Box => 'Box',
            self::Sack => 'Sack',
            self::Bilao => 'Bilao',
        };
    }

    public function abbreviation(): string
    {
        return match ($this) {
            self::Kilogram => 'kg',
            self::Gram => 'g',
            self::Liter => 'L',
            self::Milliliter => 'ml',
            self::Piece => 'pc',
            self::Dozen => 'doz',
            self::Pair => 'pair',
            self::Bundle => 'bundle',
            self::Pack => 'pack',
            self::Bag => 'bag',
            self::Tray => 'tray',
            self::Bottle => 'bottle',
            self::Can => 'can',
            self::Box => 'box',
            self::Sack => 'sack',
            self::Bilao => 'bilao',
        };
    }

    public function stockLabel(int|float $quantity): string
    {
        $formatted = number_format((float) $quantity, 0);

        return match ($this) {
            self::Kilogram => $formatted.' kg',
            self::Gram => $formatted.' g',
            self::Liter => $formatted.' L',
            self::Milliliter => $formatted.' ml',
            self::Piece => $quantity == 1 ? '1 piece' : $formatted.' pieces',
            self::Dozen => $quantity == 1 ? '1 dozen' : $formatted.' dozens',
            self::Pair => $quantity == 1 ? '1 pair' : $formatted.' pairs',
            self::Bundle => $quantity == 1 ? '1 bundle' : $formatted.' bundles',
            self::Pack => $quantity == 1 ? '1 pack' : $formatted.' packs',
            self::Bag => $quantity == 1 ? '1 bag' : $formatted.' bags',
            self::Tray => $quantity == 1 ? '1 tray' : $formatted.' trays',
            self::Bottle => $quantity == 1 ? '1 bottle' : $formatted.' bottles',
            self::Can => $quantity == 1 ? '1 can' : $formatted.' cans',
            self::Box => $quantity == 1 ? '1 box' : $formatted.' boxes',
            self::Sack => $quantity == 1 ? '1 sack' : $formatted.' sacks',
            self::Bilao => $quantity == 1 ? '1 bilao' : $formatted.' bilaos',
        };
    }

    public function priceLabel(string|float $price): string
    {
        return '₱'.number_format((float) $price, 2).' / '.$this->abbreviation();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function suggestedBaseUnits(): array
    {
        return match ($this) {
            self::Kilogram, self::Gram, self::Liter, self::Milliliter => [],
            self::Sack, self::Bag => [
                ['value' => 'kg', 'label' => 'Kilograms (kg)'],
                ['value' => 'g', 'label' => 'Grams (g)'],
            ],
            self::Tray => [
                ['value' => 'g', 'label' => 'Grams (g)'],
                ['value' => 'kg', 'label' => 'Kilograms (kg)'],
            ],
            self::Bottle, self::Can => [
                ['value' => 'ml', 'label' => 'Milliliters (ml)'],
                ['value' => 'L', 'label' => 'Liters (L)'],
            ],
            self::Box, self::Pack, self::Bundle => [
                ['value' => 'g', 'label' => 'Grams (g)'],
                ['value' => 'kg', 'label' => 'Kilograms (kg)'],
                ['value' => 'ml', 'label' => 'Milliliters (ml)'],
                ['value' => 'L', 'label' => 'Liters (L)'],
            ],
            default => [
                ['value' => 'kg', 'label' => 'Kilograms (kg)'],
                ['value' => 'g', 'label' => 'Grams (g)'],
                ['value' => 'L', 'label' => 'Liters (L)'],
                ['value' => 'ml', 'label' => 'Milliliters (ml)'],
            ],
        };
    }

    public function conversionLabel(float $quantity, string $baseUnit): string
    {
        return sprintf(
            '1 %s = %s %s',
            strtolower($this->label()),
            rtrim(rtrim(number_format($quantity, 4, '.', ''), '0'), '.'),
            $baseUnit,
        );
    }

    /**
     * @return list<self>
     */
    public static function suggestionsForCategory(string $categorySlug): array
    {
        return match (true) {
            in_array($categorySlug, ['rice', 'corn-flour', 'grains'], true) => [self::Kilogram, self::Gram, self::Bag, self::Sack, self::Pack],
            in_array($categorySlug, ['leafy-greens', 'fresh-aromatics'], true) => [self::Bundle, self::Kilogram, self::Piece, self::Gram],
            in_array($categorySlug, ['root-crops', 'fruit-vegetables', 'vegetables'], true) => [self::Kilogram, self::Gram, self::Piece, self::Bundle, self::Bag],
            in_array($categorySlug, ['fruits', 'tropical-fruits', 'citrus-fruits'], true) => [self::Kilogram, self::Piece, self::Gram, self::Dozen, self::Bag],
            in_array($categorySlug, ['bananas-plantains'], true) => [self::Bundle, self::Piece, self::Kilogram, self::Dozen],
            in_array($categorySlug, ['fresh-fish', 'seafood'], true) => [self::Kilogram, self::Piece, self::Gram],
            in_array($categorySlug, ['shellfish', 'crustaceans'], true) => [self::Kilogram, self::Gram, self::Piece],
            in_array($categorySlug, ['pork', 'beef', 'chicken', 'meat-poultry'], true) => [self::Kilogram, self::Gram, self::Piece],
            in_array($categorySlug, ['eggs', 'dairy-eggs'], true) => [self::Tray, self::Piece, self::Dozen],
            in_array($categorySlug, ['milk-dairy'], true) => [self::Bottle, self::Liter, self::Milliliter, self::Pack, self::Box],
            in_array($categorySlug, ['cooking-oils'], true) => [self::Bottle, self::Liter, self::Milliliter, self::Can],
            in_array($categorySlug, ['condiments-sauces'], true) => [self::Bottle, self::Pack, self::Can, self::Liter],
            in_array($categorySlug, ['spices-condiments-oils'], true) => [self::Pack, self::Piece, self::Bundle, self::Bottle],
            in_array($categorySlug, ['dried-fish', 'dried-salted-goods'], true) => [self::Kilogram, self::Gram, self::Pack, self::Bundle, self::Piece],
            in_array($categorySlug, ['smoked-fermented-goods'], true) => [self::Piece, self::Kilogram, self::Pack, self::Bottle, self::Can],
            in_array($categorySlug, ['kakanin-native-sweets', 'steamed-kakanin', 'native-delicacies'], true) => [self::Bilao, self::Piece, self::Tray, self::Pack, self::Box],
            in_array($categorySlug, ['frozen-processed-goods', 'frozen-ready-to-cook', 'cured-meats'], true) => [self::Pack, self::Piece, self::Kilogram, self::Gram, self::Box],
            default => [self::Piece, self::Kilogram, self::Gram, self::Bundle, self::Pack],
        };
    }
}
