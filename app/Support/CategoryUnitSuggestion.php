<?php

namespace App\Support;

use App\Enums\ProductUnit;
use Throwable;

class CategoryUnitSuggestion
{
    /**
     * @return list<ProductUnit>
     */
    public static function forCategory(string $categorySlug): array
    {
        $suggestions = self::configuredSuggestions();
        $configuredUnits = $suggestions[$categorySlug] ?? $suggestions['default'] ?? [];

        return array_values(array_filter(
            array_map(
                fn (string $unit): ?ProductUnit => ProductUnit::tryFrom($unit),
                $configuredUnits,
            ),
        ));
    }

    /**
     * @return array<string, list<string>>
     */
    private static function configuredSuggestions(): array
    {
        try {
            if (function_exists('app') && app()->bound('config')) {
                return (array) config('category-unit-suggestions', []);
            }
        } catch (Throwable) {
        }

        return require dirname(__DIR__, 2).'/config/category-unit-suggestions.php';
    }
}
