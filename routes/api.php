<?php

use App\Http\Controllers\AccountingYearController;
use App\Http\Controllers\AuthorizationController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\ReconciliationController;
use App\Http\Controllers\SkpdController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/years', [AccountingYearController::class, 'index']);
    Route::post('/years', [AccountingYearController::class, 'store']);
    Route::post('/years/{accountingYear}/activate', [AccountingYearController::class, 'activate']);

    Route::apiResource('/skpds', SkpdController::class)->only(['index','store','update']);

    Route::get('/authorizations', [AuthorizationController::class, 'index']);
    Route::post('/authorizations', [AuthorizationController::class, 'store']);

    Route::get('/reconciliations', [ReconciliationController::class, 'index']);
    Route::get('/reconciliations/{reconciliation}', [ReconciliationController::class, 'show']);
    Route::post('/reconciliations/{reconciliation}/finalize', [ReconciliationController::class, 'finalize']);
});
