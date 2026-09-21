<?php

use App\Http\Controllers\AccountingYearAdminController;
use App\Http\Controllers\AccountingYearController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AuthorizationSourceController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\MasterDataImportController;
use App\Http\Controllers\MasterReferenceController;
use App\Http\Controllers\OfficialAdminController;
use App\Http\Controllers\ReconciliationController;
use App\Http\Controllers\ReconciliationReviewController;
use App\Http\Controllers\ReconciliationCrudController;
use App\Http\Controllers\SkpdAdminController;
use App\Http\Controllers\SourceDocumentController;
use App\Http\Controllers\UserAdminController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/years', [AccountingYearController::class, 'index']);
    Route::post('/years', [AccountingYearAdminController::class, 'store']);
    Route::post('/years/{accountingYear}/activate', [AccountingYearAdminController::class, 'activate']);

    Route::apiResource('/skpds', SkpdAdminController::class)->only(['index', 'store', 'update']);
    Route::apiResource('/users', UserAdminController::class)->only(['index', 'store', 'update']);
    Route::apiResource('/officials', OfficialAdminController::class)->only(['index', 'store', 'update']);
    Route::post('/master-data/import', [MasterDataImportController::class, 'store']);
    Route::get('/master-references', [MasterReferenceController::class, 'index']);
    Route::get('/source-documents', [SourceDocumentController::class, 'index']);
    Route::post('/source-documents/import', [SourceDocumentController::class, 'store']);
    Route::post('/source-documents/import-batch', [SourceDocumentController::class, 'batch']);

    Route::get('/authorizations', [AuthorizationSourceController::class, 'index']);
    Route::post('/authorizations', [AuthorizationSourceController::class, 'store']);

    Route::get('/reconciliations', [ReconciliationController::class, 'index']);
    Route::get('/reconciliation-results', [ReconciliationReviewController::class, 'index']);
    Route::get('/reconciliation-results/{reconciliationResult}', [ReconciliationReviewController::class, 'show']);
    Route::post('/reconciliation-results/{reconciliationResult}/review', [ReconciliationReviewController::class, 'review']);
    Route::get('/reconciliation-runs', [ReconciliationController::class, 'runs']);
    Route::post('/reconciliation-runs', [ReconciliationController::class, 'run']);
    Route::get('/reconciliation-runs/{reconciliationRun}', [ReconciliationController::class, 'runShow']);
    Route::post('/reconciliations', [ReconciliationCrudController::class, 'store']);
    Route::get('/reconciliations/{reconciliation}', [ReconciliationController::class, 'show']);
    Route::put('/reconciliations/{reconciliation}', [ReconciliationCrudController::class, 'update']);
    Route::get('/reconciliations/{reconciliation}/ba', [ReconciliationController::class, 'ba']);
    Route::get('/reconciliations/{reconciliation}/ba/document', [ReconciliationController::class, 'baDocument']);
    Route::get('/reconciliations/{reconciliation}/ba/excel', [ReconciliationController::class, 'baExcel']);
    Route::post('/reconciliations/{reconciliation}/finalize', [ReconciliationController::class, 'finalize']);
});
