<?php

use App\Http\Controllers\AccountingYearController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AuthorizationSourceController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\ReconciliationController;
use App\Http\Controllers\ReconciliationCrudController;
use App\Http\Controllers\SkpdAdminController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/years', [AccountingYearController::class, 'index']);
    Route::post('/years', [AccountingYearController::class, 'store']);
    Route::post('/years/{accountingYear}/activate', [AccountingYearController::class, 'activate']);

    Route::apiResource('/skpds', SkpdAdminController::class)->only(['index', 'store', 'update']);

    Route::get('/authorizations', [AuthorizationSourceController::class, 'index']);
    Route::post('/authorizations', [AuthorizationSourceController::class, 'store']);

    Route::get('/reconciliations', [ReconciliationController::class, 'index']);
    Route::post('/reconciliations', [ReconciliationCrudController::class, 'store']);
    Route::get('/reconciliations/{reconciliation}', [ReconciliationController::class, 'show']);
    Route::put('/reconciliations/{reconciliation}', [ReconciliationCrudController::class, 'update']);
    Route::post('/reconciliations/{reconciliation}/finalize', [ReconciliationController::class, 'finalize']);
});
