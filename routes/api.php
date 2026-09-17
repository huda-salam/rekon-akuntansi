<?php

use App\Http\Controllers\ReconciliationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/reconciliations', [ReconciliationController::class, 'index']);
    Route::get('/reconciliations/{reconciliation}', [ReconciliationController::class, 'show']);
    Route::post('/reconciliations/{reconciliation}/finalize', [ReconciliationController::class, 'finalize']);
});
