<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class StripeCheckoutService
{
    /**
     * @return array{id:string,url:string}
     */
    public function createSubscriptionCheckoutSession(
        User $user,
        string $plan,
        string $priceId,
        string $successUrl,
        string $cancelUrl,
    ): array {
        $secretKey = (string) config('services.stripe.secret');

        if ($secretKey === '') {
            throw new RuntimeException('Stripe is not configured. Add STRIPE_SECRET in your environment.');
        }

        $payload = [
            'mode' => 'subscription',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'line_items[0][price]' => $priceId,
            'line_items[0][quantity]' => 1,
            'allow_promotion_codes' => 'true',
            'metadata[user_id]' => (string) $user->id,
            'metadata[plan]' => $plan,
        ];

        if ($user->stripe_customer_id) {
            $payload['customer'] = $user->stripe_customer_id;
        } else {
            $payload['customer_email'] = $user->email;
        }

        $response = Http::asForm()
            ->withBasicAuth($secretKey, '')
            ->post('https://api.stripe.com/v1/checkout/sessions', $payload);

        if (! $response->successful()) {
            throw new RuntimeException('Stripe checkout session creation failed: '.$response->body());
        }

        $data = $response->json();

        if (! is_array($data) || ! isset($data['id'], $data['url'])) {
            throw new RuntimeException('Stripe checkout session response was invalid.');
        }

        return [
            'id' => (string) $data['id'],
            'url' => (string) $data['url'],
        ];
    }

    public function verifyWebhookSignature(string $payload, ?string $signatureHeader): bool
    {
        $secret = (string) config('services.stripe.webhook_secret');

        if ($secret === '') {
            return true;
        }

        if (! is_string($signatureHeader) || $signatureHeader === '') {
            return false;
        }

        $parts = $this->parseSignatureHeader($signatureHeader);

        if (! isset($parts['t']) || ! isset($parts['v1'])) {
            return false;
        }

        $timestamp = (int) $parts['t'];

        if (abs(time() - $timestamp) > 300) {
            return false;
        }

        $signedPayload = $timestamp.'.'.$payload;
        $expectedSignature = hash_hmac('sha256', $signedPayload, $secret);

        return hash_equals($expectedSignature, $parts['v1']);
    }

    /**
     * @return array<string, string>
     */
    private function parseSignatureHeader(string $signatureHeader): array
    {
        $pairs = array_map('trim', explode(',', $signatureHeader));
        $parsed = [];

        foreach ($pairs as $pair) {
            $segments = explode('=', $pair, 2);

            if (count($segments) !== 2) {
                continue;
            }

            $parsed[$segments[0]] = $segments[1];
        }

        return $parsed;
    }
}
