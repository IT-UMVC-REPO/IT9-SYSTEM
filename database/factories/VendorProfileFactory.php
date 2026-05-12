<?php

namespace Database\Factories;

use App\Enums\TagumCoordinate;
use App\Enums\UserRole;
use App\Enums\VendorStatus;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VendorProfile>
 */
class VendorProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $storeName = fake()->company();
        $vendorPhotoUrls = [
            'https://images.unsplash.com/photo-1555939594-58d7cb561ad1?w=640&h=640&fit=crop&auto=format',
            'https://images.unsplash.com/photo-1540420773420-3366772f4999?w=640&h=640&fit=crop&auto=format',
            'https://images.unsplash.com/photo-1519996529931-28324d5a630e?w=640&h=640&fit=crop&auto=format',
            'https://images.unsplash.com/photo-1510130387422-82bed34b37e9?w=640&h=640&fit=crop&auto=format',
            'https://images.unsplash.com/photo-1607623814075-e51df1bdc82f?w=640&h=640&fit=crop&auto=format',
            'https://images.unsplash.com/photo-1596040033229-a9821ebd058d?w=640&h=640&fit=crop&auto=format',
        ];
        $storeDescriptions = [
            'Fresh market staples sourced early each morning for neighborhood shoppers.',
            'Daily produce, pantry goods, and seasonal finds from trusted local suppliers.',
            'Reliable wet-market favorites prepared for quick pickup and home cooking.',
            'A neighborhood stall focused on fresh stock, fair prices, and friendly service.',
            'Carefully selected seafood, meats, produce, and essentials for everyday meals.',
        ];

        return [
            'user_id' => User::factory(),
            'store_name' => $storeName,
            'store_description' => fake()->randomElement($storeDescriptions),
            'store_image' => fake()->randomElement($vendorPhotoUrls),
            ...TagumCoordinate::random(),
            'status' => VendorStatus::Pending,
            'rejection_reason' => null,
            'approved_at' => null,
            'created_at' => now(),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VendorStatus::Approved,
            'rejection_reason' => null,
            'approved_at' => now(),
        ])->afterCreating(function (VendorProfile $vendorProfile): void {
            $vendorProfile->user()->update([
                'role' => UserRole::Vendor,
            ]);
        });
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VendorStatus::Rejected,
            'rejection_reason' => fake()->sentence(),
            'approved_at' => null,
        ]);
    }
}
