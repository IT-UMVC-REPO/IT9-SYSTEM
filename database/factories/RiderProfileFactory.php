<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\RiderProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RiderProfile>
 */
class RiderProfileFactory extends Factory
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
            'vehicle_type' => fake()->randomElement(['motorcycle', 'bicycle', 'e-bike']),
            'plate_number' => fake()->optional()->bothify('???-####'),
            'contact_number' => fake()->phoneNumber(),
            'status' => 'pending',
            'is_available' => false,
            'current_lat' => null,
            'current_lng' => null,
            'approved_at' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'is_available' => true,
            'approved_at' => now(),
        ])->afterCreating(function (RiderProfile $riderProfile): void {
            $riderProfile->user()->update([
                'role' => UserRole::Rider,
            ]);
        });
    }
}
