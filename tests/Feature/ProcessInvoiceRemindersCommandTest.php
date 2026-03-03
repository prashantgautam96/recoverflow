<?php

use App\Mail\InvoiceReminderMail;
use App\Models\ApiKey;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceReminder;
use Illuminate\Support\Facades\Mail;

it('processes due reminders and skips reminders for paid invoices', function () {
    Mail::fake();

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
        ->expectsOutput('Failed: 0')
        ->assertSuccessful();

    Mail::assertSent(InvoiceReminderMail::class, function (InvoiceReminderMail $mail) use ($client): bool {
        return $mail->hasTo($client->email);
    });

    expect($sendReminder->refresh()->status)->toBe(InvoiceReminder::StatusSent)
        ->and($skipReminder->refresh()->status)->toBe(InvoiceReminder::StatusSkipped)
        ->and($openInvoice->refresh()->status)->toBe(Invoice::StatusOverdue);
});

it('skips reminders when client email is missing', function () {
    Mail::fake();

    $apiKey = ApiKey::factory()->create();

    $client = Client::factory()->create([
        'api_key_id' => $apiKey->id,
        'email' => null,
    ]);

    $invoice = Invoice::factory()->create([
        'api_key_id' => $apiKey->id,
        'client_id' => $client->id,
        'status' => Invoice::StatusPending,
        'due_at' => now()->subDay()->toDateString(),
    ]);

    $reminder = InvoiceReminder::factory()->create([
        'invoice_id' => $invoice->id,
        'api_key_id' => $apiKey->id,
        'status' => InvoiceReminder::StatusPending,
        'scheduled_for' => now()->subMinutes(5),
    ]);

    $this->artisan('recoverflow:process-reminders --limit=10')
        ->expectsOutput('Processed: 1')
        ->expectsOutput('Sent: 0')
        ->expectsOutput('Skipped: 1')
        ->expectsOutput('Failed: 0')
        ->assertSuccessful();

    Mail::assertNothingSent();

    expect($reminder->refresh()->status)->toBe(InvoiceReminder::StatusSkipped)
        ->and($reminder->refresh()->sent_at)->toBeNull();
});
