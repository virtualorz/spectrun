<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'github_username' => fake()->unique()->userName(),
            'github_user_id' => fake()->unique()->numberBetween(1, 9_999_999),
            'avatar_url' => fake()->imageUrl(),
            'access_token' => 'ghp_'.Str::random(36),
            'account' => fake()->unique()->userName(),
            // 明文,交給 User model 的 hashed cast 雜湊一次(勿在此先 Hash::make,會二次雜湊)
            'password' => 'password',
            'connected_at' => now(),
        ];
    }
}
