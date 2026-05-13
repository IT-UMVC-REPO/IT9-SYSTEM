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
            'vehicle_type' => fake()->randomElement(['motorcycle', 'bicycle', 'e-bike', 'e-scooter', 'tricycle', 'car', 'van']),
            'plate_number' => fake()->optional()->bothify('???-####'),
            'contact_number' => fake()->phoneNumber(),
            'bio' => fake()->optional()->sentence(8),
            'status' => 'pending',
            'is_available' => false,
            'current_lat' => null,
            'current_lng' => null,
            'rating' => 0,
            'total_ratings' => 0,
            'total_earnings' => 0,
            'average_delivery_minutes' => null,
            'acceptance_rate' => 0,
            'total_offers_received' => 0,
            'total_offers_accepted' => 0,
            'session_started_at' => null,
            'last_seen_at' => null,
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
