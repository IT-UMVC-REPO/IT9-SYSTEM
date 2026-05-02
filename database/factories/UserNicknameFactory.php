<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserNickname;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserNickname>
 */
class UserNicknameFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'target_id' => User::factory(),
            'nickname' => fake()->firstName(),
        ];
    }
}
