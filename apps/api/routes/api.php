<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Middleware\EnsureTenantContext;
use Illuminate\Support\Facades\Route;

// Health Endpoint
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'service' => 'ORBIS API',
        'framework' => 'Laravel 13.x',
        'database' => 'connected',
        'timestamp' => now()->toIso8601String(),
    ]);
});

Route::prefix('v1')->group(function () {
    // Public Authentication & Invitations
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::get('/invitations/validate/{token}', [InvitationController::class, 'validateToken']);
    Route::post('/invitations/{token}/accept', [InvitationController::class, 'accept']);

    // Authenticated User Session
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/switch-company', [AuthController::class, 'switchCompany']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // Tenant-Scoped Management
        Route::middleware(EnsureTenantContext::class)->group(function () {
            Route::get('/invitations', [InvitationController::class, 'index']);
            Route::post('/invitations', [InvitationController::class, 'store']);
            Route::post('/invitations/{id}/revoke', [InvitationController::class, 'revoke']);
        });
    });
});
