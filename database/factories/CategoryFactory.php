<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::headline(fake()->unique()->words(2, true));

        return [
            'parent_id' => null,
            'slug' => Str::slug($name),
            'name' => $name,
            'description' => fake()->sentence(),
            'image' => fake()->imageUrl(640, 640, 'food', true),
            'created_at' => now(),
        ];
    }

    public function standalone(): static
    {
        return $this->state(fn (): array => [
            'parent_id' => null,
        ]);
    }

    public function topLevel(): static
    {
        return $this->standalone();
    }

    public function childOf(Category $category): static
    {
        return $this->state(fn (): array => [
            'parent_id' => $category->id,
        ]);
    }
}
