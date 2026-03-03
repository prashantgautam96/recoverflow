<?php

use App\Models\ApiKey;
use App\Models\Invoice;

it('rejects requests without an api key', function () {
    $this->postJson('/api/v1/clients', [
        'name' => 'Acme',
    ])->assertUnauthorized();
});

it('creates clients and invoices with automated reminders', function () {
    $plainTextKey = 'rf_live_test_flow_key';

    ApiKey::factory()
        ->withPlainTextKey($plainTextKey)
        ->create([
            'monthly_quota' => 500,
        ]);

    $headers = [
        'X-Api-Key' => $plainTextKey,
    ];

    $clientResponse = $this->postJson('/api/v1/clients', [
        'name' => 'Nina Patel',
        'email' => 'nina@example.com',
        'company' => 'Patel Design Studio',
        'timezone' => 'America/New_York',
    ], $headers);

    $clientResponse
        ->assertCreated()
        ->assertJsonPath('name', 'Nina Patel');

    $invoiceResponse = $this->postJson('/api/v1/invoices', [
        'client_id' => $clientResponse->json('id'),
        'invoice_number' => 'INV-1001',
        'amount' => 1299.99,
        'currency' => 'usd',
        'issued_at' => now()->subDays(10)->toDateString(),
        'due_at' => now()->subDay()->toDateString(),
        'payment_url' => 'https://example.test/pay/inv-1001',
        'late_fee_percent' => 2.5,
    ], $headers);

    $invoiceResponse
        ->assertCreated()
        ->assertJsonPath('invoice_number', 'INV-1001')
        ->assertJsonPath('status', Invoice::StatusOverdue)
        ->assertJsonCount(4, 'reminders');

    $this->getJson('/api/v1/dashboard', $headers)
        ->assertOk()
        ->assertJsonPath('open_invoice_count', 1)
        ->assertJsonPath('overdue_cents', 129999)
        ->assertJsonPath('api_usage.monthly_quota', 500);
});

it('blocks requests when an api key has exhausted quota', function () {
    $plainTextKey = 'rf_live_test_quota_key';

    ApiKey::factory()
        ->withPlainTextKey($plainTextKey)
        ->create([
            'monthly_quota' => 1,
            'used_this_month' => 1,
            'last_used_at' => now(),
        ]);

    $this->getJson('/api/v1/dashboard', [
        'X-Api-Key' => $plainTextKey,
    ])->assertStatus(429);
});
