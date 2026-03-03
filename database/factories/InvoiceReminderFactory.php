<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceReminder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InvoiceReminder>
 */
class InvoiceReminderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'api_key_id' => fn (array $attributes): int => Invoice::query()->findOrFail($attributes['invoice_id'])->api_key_id,
            'sequence' => $this->faker->numberBetween(1, 4),
            'scheduled_for' => now()->subHour(),
            'sent_at' => null,
            'status' => InvoiceReminder::StatusPending,
            'channel' => 'email',
            'subject' => $this->faker->sentence(4),
            'body' => $this->faker->paragraph(),
        ];
    }
}
