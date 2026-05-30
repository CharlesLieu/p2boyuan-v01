<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ApplicationController;
use App\Http\Controllers\Api\InspectionTaskController;
use App\Http\Controllers\Api\PayoutController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => [
    'success' => true,
    'data' => [
        'status' => 'ok',
    ],
]);

Route::prefix('v1/auth')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

Route::middleware('auth:sanctum')
    ->prefix('v1')
    ->group(function (): void {
        Route::get('/applications', [ApplicationController::class, 'index']);
        Route::post('/applications', [ApplicationController::class, 'store'])
            ->middleware('role:STORE,SUPER_ADMIN');
        Route::get('/applications/{applicationId}', [ApplicationController::class, 'show']);
        Route::get('/applications/{applicationId}/logs', [ApplicationController::class, 'logs']);
        Route::post('/applications/{applicationId}/assign', [ApplicationController::class, 'assign'])
            ->middleware('role:AUDITOR,SUPER_ADMIN');
        Route::post('/applications/{applicationId}/approve', [ApplicationController::class, 'approve'])
            ->middleware('role:AUDITOR,SUPER_ADMIN');
        Route::post('/applications/{applicationId}/reject', [ApplicationController::class, 'reject'])
            ->middleware('role:AUDITOR,SUPER_ADMIN');
        Route::post('/applications/{applicationId}/request-supplement', [ApplicationController::class, 'requestSupplement'])
            ->middleware('role:AUDITOR,SUPER_ADMIN');
        Route::post('/applications/{applicationId}/supplement', [ApplicationController::class, 'submitSupplement'])
            ->middleware('role:STORE,SALES,SUPER_ADMIN');
        Route::post('/inspection-tasks/{inspectionTaskId}/start', [InspectionTaskController::class, 'start'])
            ->middleware('role:SALES,SUPER_ADMIN');
        Route::post('/inspection-tasks/{inspectionTaskId}/submit', [InspectionTaskController::class, 'submit'])
            ->middleware('role:SALES,SUPER_ADMIN');
        Route::post('/inspection-tasks/{inspectionTaskId}/reject', [InspectionTaskController::class, 'reject'])
            ->middleware('role:SALES,SUPER_ADMIN');
        Route::get('/payouts', [PayoutController::class, 'index'])
            ->middleware('role:CASHIER,SUPER_ADMIN');
        Route::post('/payouts/{payoutId}/confirm', [PayoutController::class, 'confirm'])
            ->middleware('role:CASHIER,SUPER_ADMIN');
    });
