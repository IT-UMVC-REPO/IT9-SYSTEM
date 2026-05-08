<?php

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Enums\ProductUnit;
use App\Models\Category;
use App\Models\Product;
use App\Models\VendorProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->words(3, true);
        $unit = fake()->randomElement([
            ProductUnit::Kilogram->value,
            ProductUnit::Kilogram->value,
            ProductUnit::Kilogram->value,
            ProductUnit::Piece->value,
            ProductUnit::Piece->value,
            ProductUnit::Bundle->value,
            ProductUnit::Tray->value,
            ProductUnit::Bottle->value,
            ProductUnit::Pack->value,
            ProductUnit::Sack->value,
            ProductUnit::Box->value,
        ]);
        $foodPhotoUrls = [
            'https://images.unsplash.com/photo-1540420773420-3366772f4999?w=640&h=640&fit=crop&auto=format',
            'https://images.unsplash.com/photo-1576045057995-568f588f82fb?w=640&h=640&fit=crop&auto=format',
            'https://images.unsplash.com/photo-1598170845058-32b9d6a5da37?w=640&h=640&fit=crop&auto=format',
            'https://images.unsplash.com/photo-1563565375-f3fdfdbefa83?w=640&h=640&fit=crop&auto=format',
            'https://images.unsplash.com/photo-1519996529931-28324d5a630e?w=640&h=640&fit=crop&auto=format',
            'https://images.unsplash.com/photo-1546548970-71785318a17b?w=640&h=640&fit=crop&auto=format',
            'https://images.unsplash.com/photo-1587735243615-c03f25aaff15?w=640&h=640&fit=crop&auto=format',
            'https://images.unsplash.com/photo-1510130387422-82bed34b37e9?w=640&h=640&fit=crop&auto=format',
            'https://images.unsplash.com/photo-1580822184713-fc5400e7fe10?w=640&h=640&fit=crop&auto=format',
            'https://images.unsplash.com/photo-1565680018434-b513d5e5fd47?w=640&h=640&fit=crop&auto=format',
            'https://images.unsplash.com/photo-1607623814075-e51df1bdc82f?w=640&h=640&fit=crop&auto=format',
            'https://images.unsplash.com/photo-1602470520998-f4a52199a3d6?w=640&h=640&fit=crop&auto=format',
            'https://images.unsplash.com/photo-1558030006-450675393462?w=640&h=640&fit=crop&auto=format',
            'https://images.unsplash.com/photo-1604503468506-a8da13d11d36?w=640&h=640&fit=crop&auto=format',
            'https://images.unsplash.com/photo-1536304993881-ff6e9eefa2a6?w=640&h=640&fit=crop&auto=format',
            'https://images.unsplash.com/photo-1518569656558-1f25e69d2221?w=640&h=640&fit=crop&auto=format',
            'https://images.unsplash.com/photo-1607863680198-23d4b2565df0?w=640&h=640&fit=crop&auto=format',
            'https://images.unsplash.com/photo-1596040033229-a9821ebd058d?w=640&h=640&fit=crop&auto=format',
            'https://images.unsplash.com/photo-1567620905732-2d1ec7ab7445?w=640&h=640&fit=crop&auto=format',
            'https://images.unsplash.com/photo-1584568694244-14fbdf83bd30?w=640&h=640&fit=crop&auto=format',
        ];
        $productDescriptions = [
            'Fresh %s selected for everyday market orders.',
            'Quality %s prepared for reliable home cooking.',
            'Market-ready %s stocked for today\'s shoppers.',
            'Well-kept %s from trusted local suppliers.',
            'Everyday %s chosen for freshness and good value.',
        ];

        return [
            'vendor_id' => VendorProfile::factory()->approved(),
            'category_id' => Category::factory()->standalone(),
            'name' => $name,
            'description' => sprintf(fake()->randomElement($productDescriptions), $name),
            'price' => fake()->randomFloat(2, 50, 500),
            'stock_quantity' => fake()->numberBetween(0, 100),
            'unit' => $unit,
            ...$this->conversionAttributes($unit),
            'image' => fake()->randomElement($foodPhotoUrls),
            'status' => ProductStatus::Inactive,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProductStatus::Active,
        ]);
    }

    /**
     * @return array{base_unit: string|null, base_unit_quantity: float|null}
     */
    private function conversionAttributes(string $unit): array
    {
        if (! fake()->boolean(30)) {
            return [
                'base_unit' => null,
                'base_unit_quantity' => null,
            ];
        }

        return match (ProductUnit::tryFrom($unit)) {
            ProductUnit::Sack => [
                'base_unit' => 'kg',
                'base_unit_quantity' => fake()->randomFloat(4, 25, 50),
            ],
            ProductUnit::Bottle => [
                'base_unit' => 'ml',
                'base_unit_quantity' => fake()->randomFloat(4, 250, 1000),
            ],
            ProductUnit::Tray => [
                'base_unit' => 'g',
                'base_unit_quantity' => fake()->randomFloat(4, 600, 1200),
            ],
            ProductUnit::Box, ProductUnit::Pack, ProductUnit::Bundle => [
                'base_unit' => 'g',
                'base_unit_quantity' => fake()->randomFloat(4, 200, 5000),
            ],
            default => [
                'base_unit' => null,
                'base_unit_quantity' => null,
            ],
        };
    }
}
