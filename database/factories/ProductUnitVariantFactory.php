<?php

namespace Database\Factories;

use App\Enums\ProductUnit;
use App\Models\Product;
use App\Models\ProductUnitVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductUnitVariant>
 */
class ProductUnitVariantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $unit = fake()->randomElement([
            ProductUnit::Kilogram,
            ProductUnit::Gram,
            ProductUnit::Piece,
            ProductUnit::Dozen,
            ProductUnit::Bundle,
            ProductUnit::Tray,
            ProductUnit::Sack,
        ]);

        return [
            'product_id' => Product::factory(),
            'unit' => $unit->value,
            'price' => fake()->randomFloat(2, 20, 800),
            'stock_quantity' => fake()->numberBetween(0, 100),
            'conversion_unit' => null,
            'conversion_unit_quantity' => null,
            'is_default' => false,
            'sort_order' => 0,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (): array => [
            'is_default' => true,
            'sort_order' => 0,
        ]);
    }
}
