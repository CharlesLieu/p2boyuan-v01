<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\ApplicationController;
use App\Http\Controllers\Api\AttachmentController;
use App\Http\Controllers\Api\InspectionTaskController;
use App\Http\Controllers\Api\MerchantController;
use App\Http\Controllers\Api\PayoutController;
use App\Http\Controllers\Api\SalesAgentController;
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
            ->middleware('role:SUPER_ADMIN');
        Route::get('/applications/{applicationId}', [ApplicationController::class, 'show']);
        Route::get('/applications/{applicationId}/logs', [ApplicationController::class, 'logs']);
        Route::get('/sales-agents', [SalesAgentController::class, 'index'])
            ->middleware('role:AUDITOR,SUPER_ADMIN');
        Route::post('/applications/{applicationId}/assign', [ApplicationController::class, 'assign'])
            ->middleware('role:AUDITOR,SUPER_ADMIN');
        Route::post('/applications/{applicationId}/approve', [ApplicationController::class, 'approve'])
            ->middleware('role:AUDITOR,SUPER_ADMIN');
        Route::post('/applications/{applicationId}/reject', [ApplicationController::class, 'reject'])
            ->middleware('role:AUDITOR,SUPER_ADMIN');
        Route::post('/applications/{applicationId}/request-supplement', [ApplicationController::class, 'requestSupplement'])
            ->middleware('role:AUDITOR,SUPER_ADMIN');
        Route::post('/applications/{applicationId}/supplement', [ApplicationController::class, 'submitSupplement'])
            ->middleware('role:SALES,SUPER_ADMIN');
        Route::post('/merchant/onboarding', [MerchantController::class, 'submitOnboarding'])
            ->middleware('role:STORE');
        Route::get('/merchant/me', [MerchantController::class, 'me'])
            ->middleware('role:STORE');
        Route::get('/merchant/vouchers', [MerchantController::class, 'vouchers'])
            ->middleware('role:STORE');
        Route::get('/merchant/vouchers/{voucherId}', [MerchantController::class, 'voucher'])
            ->middleware('role:STORE');
        Route::post('/attachments', [AttachmentController::class, 'store']);
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
        Route::get('/admin/accounts', [AdminController::class, 'accounts'])
            ->middleware('role:SUPER_ADMIN');
        Route::post('/admin/accounts', [AdminController::class, 'createAccount'])
            ->middleware('role:SUPER_ADMIN');
        Route::patch('/admin/accounts/{user}', [AdminController::class, 'updateAccount'])
            ->middleware('role:SUPER_ADMIN');
        Route::post('/admin/accounts/{user}/disable', [AdminController::class, 'disableAccount'])
            ->middleware('role:SUPER_ADMIN');
        Route::post('/admin/accounts/{user}/reset-password', [AdminController::class, 'resetPassword'])
            ->middleware('role:SUPER_ADMIN');
        Route::post('/admin/reset-demo-data', [AdminController::class, 'resetDemoData'])
            ->middleware('role:SUPER_ADMIN');
        Route::post('/admin/applications/{application}/status', [AdminController::class, 'updateApplicationStatus'])
            ->middleware('role:SUPER_ADMIN');
        Route::get('/admin/merchants', [MerchantController::class, 'adminMerchants'])
            ->middleware('role:SUPER_ADMIN');
        Route::get('/admin/merchants/{onboardingId}', [MerchantController::class, 'adminMerchant'])
            ->middleware('role:SUPER_ADMIN');
        Route::post('/admin/merchants/{onboardingId}/approve', [MerchantController::class, 'approveMerchant'])
            ->middleware('role:SUPER_ADMIN');
        Route::post('/admin/merchants/{onboardingId}/reject', [MerchantController::class, 'rejectMerchant'])
            ->middleware('role:SUPER_ADMIN');
        Route::get('/admin/merchant-vouchers', [MerchantController::class, 'adminVouchers'])
            ->middleware('role:SUPER_ADMIN');
        Route::post('/admin/merchant-vouchers', [MerchantController::class, 'createVoucher'])
            ->middleware('role:SUPER_ADMIN');
        Route::post('/admin/merchant-vouchers/{voucherId}/void', [MerchantController::class, 'voidVoucher'])
            ->middleware('role:SUPER_ADMIN');
    });
