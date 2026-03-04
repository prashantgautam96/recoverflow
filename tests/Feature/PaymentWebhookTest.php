<?php

use App\Models\Invoice;
use App\Models\InvoiceReminder;

it('marks invoice paid and skips pending reminders on valid token', function () {
    $invoice = Invoice::factory()->create([
        'status' => Invoice::StatusPending,
        'webhook_secret' => 'valid_secret_token_1234567890',
    ]);

    $pending = collect([1, 2, 3])->map(fn ($seq) => InvoiceReminder::factory()->create([
        'invoice_id' => $invoice->id,
        'api_key_id' => $invoice->api_key_id,
        'sequence' => $seq,
        'status' => InvoiceReminder::StatusPending,
    ]));

    $sent = InvoiceReminder::factory()->create([
        'invoice_id' => $invoice->id,
        'api_key_id' => $invoice->api_key_id,
        'sequence' => 4,
        'status' => InvoiceReminder::StatusSent,
    ]);

    $this->postJson('/api/v1/webhooks/payment/valid_secret_token_1234567890')
        ->assertOk()
        ->assertExactJson(['received' => true]);

    expect($invoice->fresh()->status)->toBe(Invoice::StatusPaid);
    expect($invoice->fresh()->paid_at)->not->toBeNull();

    expect(
        InvoiceReminder::whereIn('id', $pending->pluck('id'))->where('status', InvoiceReminder::StatusSkipped)->count()
    )->toBe(3);

    expect($sent->fresh()->status)->toBe(InvoiceReminder::StatusSent);
});

it('accepts a paid_at timestamp from the request body', function () {
    $invoice = Invoice::factory()->create([
        'status' => Invoice::StatusPending,
        'webhook_secret' => 'token_with_timestamp',
    ]);

    $paidAt = '2026-02-15T10:30:00Z';

    $this->postJson('/api/v1/webhooks/payment/token_with_timestamp', ['paid_at' => $paidAt])
        ->assertOk()
        ->assertExactJson(['received' => true]);

    expect($invoice->fresh()->paid_at->toDateString())->toBe('2026-02-15');
});

it('returns 200 without double-updating when called twice (idempotent)', function () {
    $invoice = Invoice::factory()->create([
        'status' => Invoice::StatusPending,
        'webhook_secret' => 'idempotent_token_abc',
    ]);

    $url = '/api/v1/webhooks/payment/idempotent_token_abc';

    $this->postJson($url)->assertOk()->assertExactJson(['received' => true]);

    $paidAt = $invoice->fresh()->paid_at;

    $this->postJson($url)->assertOk()->assertExactJson(['received' => true]);

    expect($invoice->fresh()->paid_at->toIso8601String())->toBe($paidAt->toIso8601String());
    expect($invoice->fresh()->status)->toBe(Invoice::StatusPaid);
});

it('returns 404 for an unknown token', function () {
    $this->postJson('/api/v1/webhooks/payment/nonexistent_token')
        ->assertNotFound();
});

it('returns 200 and makes no changes for an already-paid invoice', function () {
    $paidAt = now()->subDay();

    $invoice = Invoice::factory()->create([
        'status' => Invoice::StatusPaid,
        'paid_at' => $paidAt,
        'webhook_secret' => 'already_paid_token',
    ]);

    $this->postJson('/api/v1/webhooks/payment/already_paid_token')
        ->assertOk()
        ->assertExactJson(['received' => true]);

    expect($invoice->fresh()->paid_at->toIso8601String())->toBe($paidAt->toIso8601String());
});
