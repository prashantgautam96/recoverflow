<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginUserRequest;
use App\Http\Requests\RegisterUserRequest;
use App\Models\ApiKey;
use App\Models\User;
use App\Models\UserApiToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function register(RegisterUserRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'password' => $validated['password'],
            'billing_plan' => 'starter',
            'subscription_status' => 'inactive',
        ]);

        [, $authToken] = UserApiToken::issueForUser($user, 'web');
        [, $defaultApiKey] = $this->provisionDefaultApiKeyForUser($user);

        return response()->json([
            'message' => 'Account created successfully.',
            'auth_token' => $authToken,
            'default_api_key' => $defaultApiKey,
            'user' => $this->serializeUser($user->fresh() ?? $user),
        ], Response::HTTP_CREATED);
    }

    public function login(LoginUserRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::query()->where('email', strtolower($validated['email']))->first();

        if ($user === null || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'The provided credentials are incorrect.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        [, $authToken] = UserApiToken::issueForUser($user, 'web');

        $defaultApiKey = null;

        if (! $user->apiKeys()->exists()) {
            [, $defaultApiKey] = $this->provisionDefaultApiKeyForUser($user);
        }

        return response()->json([
            'message' => 'Logged in successfully.',
            'auth_token' => $authToken,
            'default_api_key' => $defaultApiKey,
            'user' => $this->serializeUser($user->fresh() ?? $user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $this->resolveAuthUser($request);

        return response()->json([
            'user' => $this->serializeUser($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->attributes->get('authToken');

        if ($token instanceof UserApiToken) {
            $token->delete();
        }

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * @return array{0:ApiKey,1:string}
     */
    private function provisionDefaultApiKeyForUser(User $user): array
    {
        $plainTextKey = ApiKey::generatePlainTextKey();

        $apiKey = $user->apiKeys()->create([
            'name' => 'Default Tenant Key',
            'owner_email' => $user->email,
            'plan' => 'starter',
            'monthly_quota' => (int) config('recoverflow.plans.starter.quota', 5000),
            'used_this_month' => 0,
            'key_hash' => ApiKey::hashPlainTextKey($plainTextKey),
            'active' => true,
        ]);

        return [$apiKey, $plainTextKey];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeUser(User $user): array
    {
        $apiKeys = $user->apiKeys()
            ->select(['id', 'name', 'owner_email', 'plan', 'monthly_quota', 'used_this_month', 'active', 'created_at'])
            ->latest()
            ->get();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'billing_plan' => $user->billing_plan,
            'subscription_status' => $user->subscription_status,
            'subscription_ends_at' => $user->subscription_ends_at?->toIso8601String(),
            'api_keys' => $apiKeys,
        ];
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
