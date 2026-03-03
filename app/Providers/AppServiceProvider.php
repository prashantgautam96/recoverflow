<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('auth-api', function (Request $request): Limit {
            $email = strtolower(trim((string) $request->input('email', '')));
            $throttleKey = $email.'|'.$request->ip();

            return Limit::perMinute(10)->by($throttleKey);
        });

        RateLimiter::for('otp-verify', function (Request $request): Limit {
            $email = strtolower(trim((string) $request->input('email', '')));
            $throttleKey = 'otp|'.$email.'|'.$request->ip();

            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}
