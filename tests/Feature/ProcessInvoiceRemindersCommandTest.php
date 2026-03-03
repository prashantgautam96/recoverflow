<?php

use App\Models\ApiKey;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceReminder;

it('processes due reminders and skips reminders for paid invoices', function () {
    $apiKey = ApiKey::factory()->create();

    $client = Client::factory()->create([
        'api_key_id' => $apiKey->id,
    ]);

    $openInvoice = Invoice::factory()->create([
        'api_key_id' => $apiKey->id,
        'client_id' => $client->id,
        'status' => Invoice::StatusPending,
        'due_at' => now()->subDay()->toDateString(),
    ]);

    $paidInvoice = Invoice::factory()->create([
        'api_key_id' => $apiKey->id,
        'client_id' => $client->id,
        'status' => Invoice::StatusPaid,
        'paid_at' => now(),
    ]);

    $sendReminder = InvoiceReminder::factory()->create([
        'invoice_id' => $openInvoice->id,
        'api_key_id' => $apiKey->id,
        'status' => InvoiceReminder::StatusPending,
        'scheduled_for' => now()->subMinutes(5),
    ]);

    $skipReminder = InvoiceReminder::factory()->create([
        'invoice_id' => $paidInvoice->id,
        'api_key_id' => $apiKey->id,
        'status' => InvoiceReminder::StatusPending,
        'scheduled_for' => now()->subMinutes(5),
    ]);

    $this->artisan('recoverflow:process-reminders --limit=10')
        ->expectsOutput('Processed: 2')
        ->expectsOutput('Sent: 1')
        ->expectsOutput('Skipped: 1')
        ->assertSuccessful();

    expect($sendReminder->refresh()->status)->toBe(InvoiceReminder::StatusSent)
        ->and($skipReminder->refresh()->status)->toBe(InvoiceReminder::StatusSkipped)
        ->and($openInvoice->refresh()->status)->toBe(Invoice::StatusOverdue);
});
