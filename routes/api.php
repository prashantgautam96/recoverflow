<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BillingController;
use App\Http\Controllers\Api\V1\ClientController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\InvoiceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:auth-api');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth-api');

    Route::middleware('auth.token')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

Route::prefix('v1/billing')->group(function () {
    Route::post('/webhook', [BillingController::class, 'webhook']);

    Route::middleware('auth.token')->group(function () {
        Route::post('/checkout-session', [BillingController::class, 'createCheckoutSession']);
    });
});

Route::prefix('v1')->middleware('api.key')->group(function () {
    Route::get('/dashboard', DashboardController::class);

    Route::apiResource('clients', ClientController::class)
        ->only(['index', 'store', 'show']);

    Route::apiResource('invoices', InvoiceController::class)
        ->only(['index', 'store', 'show']);

    Route::post('/invoices/{invoice}/mark-paid', [InvoiceController::class, 'markPaid']);
});
