<?php

use App\Models\ApiKey;
use App\Models\User;
use App\Models\UserApiToken;
use Illuminate\Support\Facades\Http;

it('registers a user and returns auth plus default api key', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Prashant Gautam',
        'email' => 'prashant@example.com',
        'password' => 'securepass123',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('user.email', 'prashant@example.com')
        ->assertJsonStructure([
            'auth_token',
            'default_api_key',
            'user' => ['id', 'api_keys'],
        ]);

    $authToken = $response->json('auth_token');

    $this->getJson('/api/v1/auth/me', [
        'Authorization' => 'Bearer '.$authToken,
    ])->assertOk()->assertJsonPath('user.name', 'Prashant Gautam');

    expect(ApiKey::query()->whereHas('user', fn ($query) => $query->where('email', 'prashant@example.com'))->count())->toBe(1);
});

it('logs in a user and issues auth token', function () {
    $user = User::factory()->create([
        'email' => 'billing@example.com',
        'password' => 'password123',
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'billing@example.com',
        'password' => 'password123',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonStructure([
            'auth_token',
            'user' => ['billing_plan', 'subscription_status'],
        ]);
});

it('throttles repeated login attempts', function () {
    User::factory()->create([
        'email' => 'throttle@example.com',
        'password' => 'password123',
    ]);

    for ($attempt = 1; $attempt <= 11; $attempt++) {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'throttle@example.com',
            'password' => 'incorrect-password',
        ]);
    }

    $response->assertStatus(429);
});

it('creates a stripe checkout session for authenticated users', function () {
    Http::fake([
        'https://api.stripe.com/v1/checkout/sessions' => Http::response([
            'id' => 'cs_test_123',
            'url' => 'https://checkout.stripe.com/c/pay/cs_test_123',
        ], 200),
    ]);

    config()->set('services.stripe.secret', 'sk_test_local');
    config()->set('recoverflow.plans.growth.stripe_price_id', 'price_growth_123');

    $user = User::factory()->create([
        'email' => 'paying@example.com',
    ]);

    [, $authToken] = UserApiToken::issueForUser($user, 'web');

    $response = $this->postJson('/api/v1/billing/checkout-session', [
        'plan' => 'growth',
    ], [
        'Authorization' => 'Bearer '.$authToken,
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('session_id', 'cs_test_123')
        ->assertJsonPath('plan', 'growth');

    Http::assertSent(function ($request) use ($user): bool {
        return $request->url() === 'https://api.stripe.com/v1/checkout/sessions'
            && $request['mode'] === 'subscription'
            && $request['metadata[user_id]'] === (string) $user->id
            && $request['metadata[plan]'] === 'growth'
            && $request['line_items[0][price]'] === 'price_growth_123';
    });
});

it('updates subscription state from stripe webhook payload', function () {
    config()->set('services.stripe.webhook_secret', 'whsec_test_secret');

    $user = User::factory()->create([
        'email' => 'webhook@example.com',
    ]);

    $apiKey = ApiKey::factory()->create([
        'user_id' => $user->id,
        'plan' => 'starter',
        'monthly_quota' => 5000,
    ]);

    $payload = json_encode([
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'metadata' => [
                    'user_id' => (string) $user->id,
                    'plan' => 'growth',
                ],
                'customer' => 'cus_test_123',
                'subscription' => 'sub_test_123',
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    $timestamp = time();
    $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test_secret');

    $this->call('POST', '/api/v1/billing/webhook', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
    ], $payload)->assertOk();

    $user->refresh();
    $apiKey->refresh();

    expect($user->billing_plan)->toBe('growth')
        ->and($user->subscription_status)->toBe('active')
        ->and($user->stripe_customer_id)->toBe('cus_test_123')
        ->and($user->stripe_subscription_id)->toBe('sub_test_123')
        ->and($apiKey->plan)->toBe('growth')
        ->and($apiKey->monthly_quota)->toBe(25000);
});

it('rejects stripe webhooks when webhook secret is not configured', function () {
    config()->set('services.stripe.webhook_secret', '');

    $payload = json_encode([
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'metadata' => [
                    'user_id' => '1',
                    'plan' => 'starter',
                ],
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    $this->call('POST', '/api/v1/billing/webhook', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
    ], $payload)
        ->assertBadRequest()
        ->assertJsonPath('message', 'Invalid Stripe webhook signature.');
});
