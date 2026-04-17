<?php

namespace Database\Factories;

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
        return [
            'user_id' => User::factory(),
            'store_name' => fake()->company(),
            'store_description' => fake()->paragraph(),
            'store_image' => fake()->imageUrl(640, 640, 'business', true),
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
