<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCheckoutSessionRequest;
use App\Models\User;
use App\Services\StripeCheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class BillingController extends Controller
{
    public function __construct(private StripeCheckoutService $stripeCheckoutService) {}

    public function createCheckoutSession(CreateCheckoutSessionRequest $request): JsonResponse
    {
        $user = $this->resolveAuthUser($request);
        $validated = $request->validated();
        $plan = $validated['plan'];
        $planConfig = config("recoverflow.plans.{$plan}");

        if (! is_array($planConfig) || empty($planConfig['stripe_price_id'])) {
            return response()->json([
                'message' => 'Stripe price is missing for the selected plan.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $session = $this->stripeCheckoutService->createSubscriptionCheckoutSession(
                user: $user,
                plan: $plan,
                priceId: (string) $planConfig['stripe_price_id'],
                successUrl: $validated['success_url'] ?? (string) config('recoverflow.checkout_success_url'),
                cancelUrl: $validated['cancel_url'] ?? (string) config('recoverflow.checkout_cancel_url'),
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'checkout_url' => $session['url'],
            'session_id' => $session['id'],
            'plan' => $plan,
        ]);
    }

    public function webhook(Request $request): JsonResponse
    {
        $payload = (string) $request->getContent();

        if (! $this->stripeCheckoutService->verifyWebhookSignature($payload, $request->header('Stripe-Signature'))) {
            return response()->json([
                'message' => 'Invalid Stripe webhook signature.',
            ], Response::HTTP_BAD_REQUEST);
        }

        /** @var array<string, mixed>|null $event */
        $event = json_decode($payload, true);

        if (! is_array($event) || ! isset($event['type'], $event['data']['object']) || ! is_array($event['data']['object'])) {
            return response()->json([
                'message' => 'Invalid webhook payload.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $eventType = (string) $event['type'];
        $object = $event['data']['object'];

        if ($eventType === 'checkout.session.completed') {
            $this->handleCheckoutSessionCompleted($object);
        }

        if (in_array($eventType, ['customer.subscription.updated', 'customer.subscription.deleted'], true)) {
            $this->handleSubscriptionUpdate($object);
        }

        return response()->json(['received' => true]);
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function handleCheckoutSessionCompleted(array $session): void
    {
        $metadata = is_array($session['metadata'] ?? null) ? $session['metadata'] : [];
        $userId = isset($metadata['user_id']) ? (int) $metadata['user_id'] : null;

        $user = $userId ? User::query()->find($userId) : null;

        if ($user === null && isset($session['customer_email']) && is_string($session['customer_email'])) {
            $user = User::query()->where('email', strtolower($session['customer_email']))->first();
        }

        if ($user === null) {
            return;
        }

        $plan = isset($metadata['plan']) && is_string($metadata['plan']) ? $metadata['plan'] : $user->billing_plan;

        $user->forceFill([
            'billing_plan' => $plan,
            'stripe_customer_id' => is_string($session['customer'] ?? null) ? $session['customer'] : $user->stripe_customer_id,
            'stripe_subscription_id' => is_string($session['subscription'] ?? null) ? $session['subscription'] : $user->stripe_subscription_id,
            'subscription_status' => 'active',
        ])->save();

        $this->syncApiKeyQuotaForPlan($user, $plan);
    }

    /**
     * @param  array<string, mixed>  $subscription
     */
    private function handleSubscriptionUpdate(array $subscription): void
    {
        $customerId = $subscription['customer'] ?? null;

        if (! is_string($customerId) || $customerId === '') {
            return;
        }

        $user = User::query()->where('stripe_customer_id', $customerId)->first();

        if ($user === null) {
            return;
        }

        $status = is_string($subscription['status'] ?? null) ? $subscription['status'] : 'inactive';

        $periodEnd = null;

        if (is_numeric($subscription['current_period_end'] ?? null)) {
            $periodEnd = Carbon::createFromTimestamp((int) $subscription['current_period_end']);
        }

        $user->forceFill([
            'stripe_subscription_id' => is_string($subscription['id'] ?? null) ? $subscription['id'] : $user->stripe_subscription_id,
            'subscription_status' => $status,
            'subscription_ends_at' => $periodEnd,
        ])->save();

        if (in_array($status, ['canceled', 'unpaid', 'incomplete_expired'], true)) {
            $user->forceFill([
                'billing_plan' => 'starter',
            ])->save();

            $this->syncApiKeyQuotaForPlan($user, 'starter');
        }
    }

    private function syncApiKeyQuotaForPlan(User $user, string $plan): void
    {
        $quota = (int) config("recoverflow.plans.{$plan}.quota", 5000);

        $user->apiKeys()->update([
            'plan' => $plan,
            'monthly_quota' => max(1, $quota),
        ]);
    }

    private function resolveAuthUser(Request $request): User
    {
        $user = $request->attributes->get('authUser');

        if (! $user instanceof User) {
            abort(Response::HTTP_UNAUTHORIZED, 'Authentication context missing.');
        }

        return $user;
    }
}
