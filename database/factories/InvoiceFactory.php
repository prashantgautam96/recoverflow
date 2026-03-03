<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Invoice>
 */
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'api_key_id' => fn (array $attributes): int => Client::query()->findOrFail($attributes['client_id'])->api_key_id,
            'invoice_number' => 'INV-'.$this->faker->unique()->numerify('######'),
            'currency' => 'USD',
            'amount_cents' => $this->faker->numberBetween(1000, 300000),
            'issued_at' => now()->subDays(10)->toDateString(),
            'due_at' => now()->addDays(7)->toDateString(),
            'status' => Invoice::StatusPending,
            'paid_at' => null,
            'payment_url' => $this->faker->url(),
            'late_fee_percent' => 2.5,
            'last_reminder_sent_at' => null,
        ];
    }
}
