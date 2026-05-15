<?php

namespace App\Support;

use App\Enums\ProductUnit;
use NumberFormatter;

class UnitFormatter
{
    public static function format(ProductUnit $unit, int|float $quantity, ?string $locale = null): string
    {
        return trim(self::number($quantity, $locale).' '.self::unitLabel($unit, $quantity));
    }

    public static function pricePerUnit(ProductUnit $unit, string|float $price, ?string $locale = null): string
    {
        return self::currency((float) $price, $locale).' / '.$unit->symbol();
    }

    public static function currency(float $amount, ?string $locale = null): string
    {
        if (class_exists(NumberFormatter::class)) {
            $formatter = new NumberFormatter($locale ?? 'en_PH', NumberFormatter::CURRENCY);
            $formatted = $formatter->formatCurrency($amount, 'PHP');

            if ($formatted !== false) {
                return $formatted;
            }
        }

        return "\u{20B1}".number_format($amount, 2);
    }

    public static function number(int|float $quantity, ?string $locale = null): string
    {
        if (class_exists(NumberFormatter::class)) {
            $formatter = new NumberFormatter($locale ?? app()->getLocale(), NumberFormatter::DECIMAL);
            $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, 0);
            $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 4);
            $formatted = $formatter->format($quantity);

            if ($formatted !== false) {
                return $formatted;
            }
        }

        $decimals = floor((float) $quantity) === (float) $quantity ? 0 : 4;
        $formatted = number_format((float) $quantity, $decimals, '.', ',');

        return $decimals === 0 ? $formatted : rtrim(rtrim($formatted, '0'), '.');
    }

    private static function unitLabel(ProductUnit $unit, int|float $quantity): string
    {
        if ($unit->isWeightBased() || $unit->isVolumeBased()) {
            return $unit->symbol();
        }

        return abs((float) $quantity) === 1.0
            ? self::countLabels($unit)[0]
            : self::countLabels($unit)[1];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function countLabels(ProductUnit $unit): array
    {
        return match ($unit) {
            ProductUnit::Piece => ['piece', 'pieces'],
            ProductUnit::Dozen => ['dozen', 'dozen'],
            ProductUnit::Pair => ['pair', 'pairs'],
            ProductUnit::Bundle => ['bundle', 'bundles'],
            ProductUnit::Pack => ['pack', 'packs'],
            ProductUnit::Bag => ['bag', 'bags'],
            ProductUnit::Tray => ['tray', 'trays'],
            ProductUnit::Bottle => ['bottle', 'bottles'],
            ProductUnit::Can => ['can', 'cans'],
            ProductUnit::Box => ['box', 'boxes'],
            ProductUnit::Sack => ['sack', 'sacks'],
            ProductUnit::Bilao => ['bilao', 'bilaos'],
            default => [strtolower($unit->label()), strtolower($unit->label()).'s'],
        };
    }
}
