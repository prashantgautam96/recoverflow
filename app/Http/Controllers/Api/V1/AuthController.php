<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginUserRequest;
use App\Http\Requests\RegisterUserRequest;
use App\Http\Requests\VerifyRegistrationOtpRequest;
use App\Mail\RegistrationOtpMail;
use App\Models\ApiKey;
use App\Models\PendingRegistration;
use App\Models\User;
use App\Models\UserApiToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function register(RegisterUserRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $email = strtolower($validated['email']);

        if (User::query()->where('email', $email)->exists()) {
            return response()->json([
                'message' => 'An account already exists for this email.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $otp = (string) random_int(100000, 999999);
        $otpTtlMinutes = (int) config('recoverflow.registration_otp_ttl_minutes', 10);

        PendingRegistration::query()->updateOrCreate([
            'email' => $email,
        ], [
            'name' => $validated['name'],
            'password' => Hash::make($validated['password']),
            'otp_hash' => PendingRegistration::hashOtp($email, $otp),
            'otp_expires_at' => now()->addMinutes($otpTtlMinutes),
            'attempts' => 0,
        ]);

        Mail::to($email)->send(new RegistrationOtpMail(
            name: $validated['name'],
            otp: $otp,
            expiresInMinutes: $otpTtlMinutes
        ));

        return response()->json([
            'message' => 'OTP sent to your email. Verify OTP to create your account.',
            'otp_sent' => true,
            'email' => $email,
        ], Response::HTTP_CREATED);
    }

    public function verifyRegistrationOtp(VerifyRegistrationOtpRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $email = strtolower($validated['email']);
        $pendingRegistration = PendingRegistration::query()
            ->where('email', $email)
            ->first();

        if ($pendingRegistration === null) {
            return response()->json([
                'message' => 'No pending registration found. Please request a new OTP.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($pendingRegistration->otp_expires_at->isPast()) {
            $pendingRegistration->delete();

            return response()->json([
                'message' => 'OTP expired. Please request a new OTP.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $isValidOtp = hash_equals(
            $pendingRegistration->otp_hash,
            PendingRegistration::hashOtp($email, $validated['otp'])
        );

        if (! $isValidOtp) {
            $pendingRegistration->increment('attempts');

            if ($pendingRegistration->attempts >= 5) {
                $pendingRegistration->delete();
            }

            return response()->json([
                'message' => 'Invalid OTP code.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (User::query()->where('email', $email)->exists()) {
            $pendingRegistration->delete();

            return response()->json([
                'message' => 'An account already exists for this email. Please log in.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $authPayload = DB::transaction(function () use ($pendingRegistration, $email): array {
            $user = User::query()->create([
                'name' => $pendingRegistration->name,
                'email' => $email,
                'password' => $pendingRegistration->password,
                'email_verified_at' => now(),
                'billing_plan' => 'starter',
                'subscription_status' => 'inactive',
            ]);

            [, $authToken] = UserApiToken::issueForUser($user, 'web');
            [, $defaultApiKey] = $this->provisionDefaultApiKeyForUser($user);

            $pendingRegistration->delete();

            return [
                'user' => $user->fresh() ?? $user,
                'auth_token' => $authToken,
                'default_api_key' => $defaultApiKey,
            ];
        });

        return response()->json([
            'message' => 'Account created successfully.',
            'auth_token' => $authPayload['auth_token'],
            'default_api_key' => $authPayload['default_api_key'],
            'user' => $this->serializeUser($authPayload['user']),
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

        if ($user->email_verified_at === null) {
            return response()->json([
                'message' => 'Verify your email with OTP before logging in.',
            ], Response::HTTP_FORBIDDEN);
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
