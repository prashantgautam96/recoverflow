<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureValidApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $plainTextKey = $this->extractApiKeyFromRequest($request);

        if ($plainTextKey === null) {
            return $this->unauthorizedResponse('Missing API key. Send X-Api-Key header.');
        }

        $usageReservation = DB::transaction(function () use ($plainTextKey): array {
            $apiKey = ApiKey::query()
                ->where('key_hash', ApiKey::hashPlainTextKey($plainTextKey))
                ->where('active', true)
                ->lockForUpdate()
                ->first();

            if ($apiKey === null) {
                return [
                    'status' => 'invalid',
                    'api_key' => null,
                ];
            }

            if (! $apiKey->consumeQuotaUnit()) {
                return [
                    'status' => 'quota_exceeded',
                    'api_key' => $apiKey,
                ];
            }

            return [
                'status' => 'ok',
                'api_key' => $apiKey,
            ];
        });

        if ($usageReservation['status'] === 'invalid') {
            return $this->unauthorizedResponse('Invalid API key.');
        }

        if ($usageReservation['status'] === 'quota_exceeded') {
            return response()->json([
                'message' => 'Monthly API quota exceeded for this key.',
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $apiKey = $usageReservation['api_key'];

        if (! $apiKey instanceof ApiKey) {
            return $this->unauthorizedResponse('Invalid API key.');
        }

        if ($apiKey->user_id !== null && $apiKey->user?->email_verified_at === null) {
            return response()->json([
                'message' => 'Verify your email with OTP before using this API key.',
            ], Response::HTTP_FORBIDDEN);
        }

        $request->attributes->set('apiKey', $apiKey);

        return $next($request);
    }

    private function extractApiKeyFromRequest(Request $request): ?string
    {
        $headerKey = $request->header('X-Api-Key');

        if (is_string($headerKey) && $headerKey !== '') {
            return trim($headerKey);
        }

        $bearerToken = $request->bearerToken();

        if (is_string($bearerToken) && $bearerToken !== '') {
            return trim($bearerToken);
        }

        return null;
    }

    private function unauthorizedResponse(string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
        ], Response::HTTP_UNAUTHORIZED);
    }
}
