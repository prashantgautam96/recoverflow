<?php

namespace App\Http\Middleware;

use App\Models\UserApiToken;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAuthenticatedUserToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $plainTextToken = $request->bearerToken();

        if (! is_string($plainTextToken) || trim($plainTextToken) === '') {
            return $this->unauthorizedResponse('Missing user access token. Send Authorization: Bearer <token>.');
        }

        $token = UserApiToken::findValidByPlainTextToken($plainTextToken);

        if ($token === null || $token->user === null) {
            return $this->unauthorizedResponse('Invalid or expired user access token.');
        }

        if ($token->user->email_verified_at === null) {
            return response()->json([
                'message' => 'Verify your email with OTP before accessing this resource.',
            ], Response::HTTP_FORBIDDEN);
        }

        $token->forceFill([
            'last_used_at' => now(),
        ])->save();

        $request->attributes->set('authUser', $token->user);
        $request->attributes->set('authToken', $token);

        return $next($request);
    }

    private function unauthorizedResponse(string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
        ], Response::HTTP_UNAUTHORIZED);
    }
}
