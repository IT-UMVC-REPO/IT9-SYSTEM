<?php

namespace Database\Factories;

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reporter_id' => User::factory(),
            'reported_user_id' => User::factory()
                ->vendor()
                ->afterCreating(function (User $user): void {
                    if ($user->vendorProfile === null) {
                        VendorProfile::factory()->for($user, 'user')->approved()->create();
                    }
                }),
            'order_id' => null,
            'reporter_role' => 'customer',
            'reason' => fake()->randomElement(ReportReason::availableFor('customer')),
            'description' => fake()->optional()->sentence(),
            'status' => ReportStatus::Open,
            'reviewed_by' => null,
            'admin_notes' => null,
            'reviewed_at' => null,
        ];
    }

    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReportStatus::Open,
            'reviewed_by' => null,
            'admin_notes' => null,
            'reviewed_at' => null,
        ]);
    }

    public function reviewed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReportStatus::Reviewed,
            'reviewed_by' => User::factory()->admin(),
            'admin_notes' => fake()->sentence(),
            'reviewed_at' => Carbon::now(),
        ]);
    }

    public function dismissed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReportStatus::Dismissed,
            'reviewed_by' => User::factory()->admin(),
            'admin_notes' => null,
            'reviewed_at' => Carbon::now(),
        ]);
    }
}
