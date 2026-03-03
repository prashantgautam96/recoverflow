<?php

use App\Mail\RegistrationOtpMail;
use App\Models\ApiKey;
use App\Models\PendingRegistration;
use App\Models\User;
use App\Models\UserApiToken;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

it('sends registration otp and stores pending registration data', function () {
    Mail::fake();

    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Prashant Gautam',
        'email' => 'prashant@example.com',
        'password' => 'securepass123',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('otp_sent', true)
        ->assertJsonPath('email', 'prashant@example.com');

    Mail::assertSent(RegistrationOtpMail::class, function (RegistrationOtpMail $mail): bool {
        return $mail->hasTo('prashant@example.com');
    });

    expect(PendingRegistration::query()->where('email', 'prashant@example.com')->exists())->toBeTrue()
        ->and(User::query()->where('email', 'prashant@example.com')->exists())->toBeFalse();
});

it('verifies registration otp and creates account with auth token', function () {
    PendingRegistration::query()->create([
        'name' => 'Prashant Gautam',
        'email' => 'prashant@example.com',
        'password' => Hash::make('securepass123'),
        'otp_hash' => PendingRegistration::hashOtp('prashant@example.com', '123456'),
        'otp_expires_at' => now()->addMinutes(10),
        'attempts' => 0,
    ]);

    $response = $this->postJson('/api/v1/auth/register/verify-otp', [
        'email' => 'prashant@example.com',
        'otp' => '123456',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('user.email', 'prashant@example.com')
        ->assertJsonStructure([
            'auth_token',
            'default_api_key',
            'user' => ['id', 'api_keys'],
        ]);

    $user = User::query()->where('email', 'prashant@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user?->email_verified_at)->not->toBeNull()
        ->and(PendingRegistration::query()->where('email', 'prashant@example.com')->exists())->toBeFalse()
        ->and(ApiKey::query()->where('user_id', $user?->id)->count())->toBe(1);
});

it('rejects invalid otp during registration verification', function () {
    PendingRegistration::query()->create([
        'name' => 'Prashant Gautam',
        'email' => 'prashant@example.com',
        'password' => Hash::make('securepass123'),
        'otp_hash' => PendingRegistration::hashOtp('prashant@example.com', '123456'),
        'otp_expires_at' => now()->addMinutes(10),
        'attempts' => 0,
    ]);

    $this->postJson('/api/v1/auth/register/verify-otp', [
        'email' => 'prashant@example.com',
        'otp' => '000000',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Invalid OTP code.');
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

it('blocks login for unverified users', function () {
    User::factory()->unverified()->create([
        'email' => 'pending@example.com',
        'password' => 'password123',
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'pending@example.com',
        'password' => 'password123',
    ])
        ->assertForbidden()
        ->assertJsonPath('message', 'Verify your email with OTP before logging in.');
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
