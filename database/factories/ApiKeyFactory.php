<?php

namespace Database\Factories;

use App\Models\ApiKey;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ApiKey>
 */
class ApiKeyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => null,
            'name' => $this->faker->company().' API Key',
            'owner_email' => $this->faker->safeEmail(),
            'plan' => $this->faker->randomElement(['starter', 'growth']),
            'monthly_quota' => $this->faker->numberBetween(1000, 10000),
            'used_this_month' => 0,
            'key_hash' => ApiKey::hashPlainTextKey(ApiKey::generatePlainTextKey()),
            'active' => true,
            'last_used_at' => null,
        ];
    }

    public function withPlainTextKey(string $plainTextKey): static
    {
        return $this->state(fn (): array => [
            'key_hash' => ApiKey::hashPlainTextKey($plainTextKey),
        ]);
    }
}
