<?php

use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\MembershipController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\TaskController;
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
            // Dashboard Metrics
            Route::get('/dashboard/metrics', [DashboardController::class, 'metrics']);

            // Invitations
            Route::get('/invitations', [InvitationController::class, 'index']);
            Route::post('/invitations', [InvitationController::class, 'store']);
            Route::post('/invitations/{id}/revoke', [InvitationController::class, 'revoke']);

            // Members
            Route::get('/members', [MembershipController::class, 'index']);
            Route::put('/members/{id}/role', [MembershipController::class, 'updateRole']);
            Route::post('/members/{id}/deactivate', [MembershipController::class, 'deactivate']);

            // Branches
            Route::get('/branches', [BranchController::class, 'index']);
            Route::post('/branches', [BranchController::class, 'store']);
            Route::get('/branches/{id}', [BranchController::class, 'show']);
            Route::put('/branches/{id}', [BranchController::class, 'update']);
            Route::post('/branches/{id}/archive', [BranchController::class, 'archive']);

            // Departments
            Route::get('/departments', [DepartmentController::class, 'index']);
            Route::post('/departments', [DepartmentController::class, 'store']);
            Route::get('/departments/{id}', [DepartmentController::class, 'show']);
            Route::put('/departments/{id}', [DepartmentController::class, 'update']);
            Route::post('/departments/{id}/archive', [DepartmentController::class, 'archive']);

            // Projects
            Route::get('/projects', [ProjectController::class, 'index']);
            Route::post('/projects', [ProjectController::class, 'store']);
            Route::get('/projects/{id}', [ProjectController::class, 'show']);
            Route::put('/projects/{id}', [ProjectController::class, 'update']);
            Route::post('/projects/{id}/archive', [ProjectController::class, 'archive']);

            // Tasks
            Route::get('/tasks', [TaskController::class, 'index']);
            Route::post('/tasks', [TaskController::class, 'store']);
            Route::get('/tasks/{id}', [TaskController::class, 'show']);
            Route::put('/tasks/{id}', [TaskController::class, 'update']);
            Route::post('/tasks/{id}/transition', [TaskController::class, 'transition']);
            Route::post('/tasks/{id}/comments', [TaskController::class, 'addComment']);
            Route::post('/tasks/{id}/archive', [TaskController::class, 'archive']);

            // Alerts
            Route::get('/alerts', [AlertController::class, 'index']);
            Route::post('/alerts/{id}/acknowledge', [AlertController::class, 'acknowledge']);
            Route::post('/alerts/{id}/resolve', [AlertController::class, 'resolve']);

            // Reports / Export
            Route::get('/reports/tasks-csv', [ReportController::class, 'tasksCsv']);
        });
    });
});
