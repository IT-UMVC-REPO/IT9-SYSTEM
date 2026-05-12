<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\VendorCustomerStar;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VendorCustomerStar>
 */
class VendorCustomerStarFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vendor_user_id' => User::factory()->vendor(),
            'customer_id' => User::factory(),
            'created_at' => now(),
        ];
    }
}
