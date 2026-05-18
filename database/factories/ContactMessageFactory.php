<?php

namespace Database\Factories;

use App\Models\ContactMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactMessage>
 */
class ContactMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => '0917'.fake()->numerify('#######'),
            'subject' => fake()->randomElement(['general_inquiry', 'vendor_support', 'rider_support', 'order_issue', 'report_user', 'billing', 'other']),
            'message' => fake()->paragraph(3),
            'attachment_path' => null,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'is_read' => false,
            'read_at' => null,
        ];
    }
}
