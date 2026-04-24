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
        $categoryPhotoUrls = [
            'https://images.unsplash.com/photo-1540420773420-3366772f4999?w=640&h=640&fit=crop&auto=format',
            'https://images.unsplash.com/photo-1576045057995-568f588f82fb?w=640&h=640&fit=crop&auto=format',
            'https://images.unsplash.com/photo-1519996529931-28324d5a630e?w=640&h=640&fit=crop&auto=format',
            'https://images.unsplash.com/photo-1510130387422-82bed34b37e9?w=640&h=640&fit=crop&auto=format',
            'https://images.unsplash.com/photo-1607623814075-e51df1bdc82f?w=640&h=640&fit=crop&auto=format',
            'https://images.unsplash.com/photo-1586201375761-83865001e31c?w=640&h=640&fit=crop&auto=format',
            'https://images.unsplash.com/photo-1607863680198-23d4b2565df0?w=640&h=640&fit=crop&auto=format',
            'https://images.unsplash.com/photo-1596040033229-a9821ebd058d?w=640&h=640&fit=crop&auto=format',
            'https://images.unsplash.com/photo-1567620905732-2d1ec7ab7445?w=640&h=640&fit=crop&auto=format',
            'https://images.unsplash.com/photo-1584568694244-14fbdf83bd30?w=640&h=640&fit=crop&auto=format',
        ];

        return [
            'parent_id' => null,
            'slug' => Str::slug($name),
            'name' => $name,
            'description' => fake()->sentence(),
            'image' => fake()->randomElement($categoryPhotoUrls),
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
