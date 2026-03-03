<?php

namespace Database\Factories;

use App\Models\ApiKey;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Client>
 */
class ClientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'api_key_id' => ApiKey::factory(),
            'name' => $this->faker->name(),
            'email' => $this->faker->safeEmail(),
            'company' => $this->faker->company(),
            'timezone' => 'UTC',
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
