<?php

$applicationUrl = rtrim((string) env('APP_URL', 'http://localhost'), '/');

return [
    'auth_token_ttl_days' => (int) env('RECOVERFLOW_AUTH_TOKEN_TTL_DAYS', 30),
    'registration_otp_ttl_minutes' => (int) env('RECOVERFLOW_REGISTRATION_OTP_TTL_MINUTES', 10),

    'checkout_success_url' => env('RECOVERFLOW_CHECKOUT_SUCCESS_URL', $applicationUrl.'/app?section=billing&checkout=success'),
    'checkout_cancel_url' => env('RECOVERFLOW_CHECKOUT_CANCEL_URL', $applicationUrl.'/app?section=billing&checkout=cancel'),

    'plans' => [
        'starter' => [
            'label' => 'Starter',
            'monthly_price_usd' => 9,
            'quota' => 5000,
            'stripe_price_id' => env('STRIPE_PRICE_STARTER'),
        ],
        'growth' => [
            'label' => 'Growth',
            'monthly_price_usd' => 19,
            'quota' => 25000,
            'stripe_price_id' => env('STRIPE_PRICE_GROWTH'),
        ],
        'scale' => [
            'label' => 'Scale',
            'monthly_price_usd' => 49,
            'quota' => 100000,
            'stripe_price_id' => env('STRIPE_PRICE_SCALE'),
        ],
    ],
];
