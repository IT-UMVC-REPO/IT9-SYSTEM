<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->admin()->create([
            'name' => 'Marketplace Admin',
            'email' => 'admin@example.com',
        ]);

        $vendorUser = User::factory()->vendor()->create([
            'name' => 'Fresh Vendor',
            'email' => 'vendor@example.com',
        ]);

        $vendorProfile = VendorProfile::factory()
            ->for($vendorUser, 'user')
            ->approved()
            ->create([
                'store_name' => 'Fresh Vendor Market',
                'store_description' => 'Daily market goods from an approved vendor.',
            ]);

        $categories = collect([
            ['name' => 'Vegetables', 'description' => 'Fresh local vegetables.'],
            ['name' => 'Fruits', 'description' => 'Seasonal fruit selections.'],
            ['name' => 'Snacks', 'description' => 'Ready-to-eat marketplace snacks.'],
        ])->map(fn (array $category) => Category::factory()->create($category));

        $categories->each(function (Category $category) use ($vendorProfile): void {
            Product::factory()
                ->count(2)
                ->for($vendorProfile, 'vendor')
                ->for($category)
                ->active()
                ->create();
        });

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
