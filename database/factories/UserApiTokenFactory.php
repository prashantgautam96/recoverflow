<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserApiToken;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserApiToken>
 */
class UserApiTokenFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => 'web',
            'token_hash' => UserApiToken::hashPlainTextToken('rf_user_'.$this->faker->unique()->lexify('????????????????????????????')),
            'last_used_at' => null,
            'expires_at' => now()->addDays(30),
        ];
    }
}
