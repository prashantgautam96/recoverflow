<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureValidApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $plainTextKey = $this->extractApiKeyFromRequest($request);

        if ($plainTextKey === null) {
            return $this->unauthorizedResponse('Missing API key. Send X-Api-Key header.');
        }

        $apiKey = ApiKey::findActiveByPlainTextKey($plainTextKey);

        if ($apiKey === null) {
            return $this->unauthorizedResponse('Invalid API key.');
        }

        if ($apiKey->hasReachedQuota()) {
            return response()->json([
                'message' => 'Monthly API quota exceeded for this key.',
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $apiKey->registerUsage();

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
