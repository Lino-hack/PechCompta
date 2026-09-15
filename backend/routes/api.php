<?php

use App\Http\Controllers\Api\AchatController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChargeController;
use App\Http\Controllers\Api\CycleController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\ReferentielController;
use App\Http\Controllers\Api\StatsController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);

    Route::get('/achats/today', [AchatController::class, 'getTodayAchats']);
    Route::post('/achats', [AchatController::class, 'store']);
    Route::delete('/achats/lignes/{id}', [AchatController::class, 'destroyLigne']);

    Route::get('/charges/today', [ChargeController::class, 'getToday']);
    Route::post('/charges/today', [ChargeController::class, 'updateToday']);

    Route::get('/cycles', [CycleController::class, 'index']);
    Route::post('/cycles', [CycleController::class, 'store']);
    Route::get('/cycles/{id}', [CycleController::class, 'getDetails']);
    Route::post('/cycles/{id}/close', [CycleController::class, 'close']);
    Route::post('/cycles/{id}/frais-libres', [CycleController::class, 'addFraisLibre']);
    Route::delete('/cycles/{id}/frais-libres/{fraisId}', [CycleController::class, 'destroyFraisLibre']);

    Route::get('/referentiels/types-poisson', [ReferentielController::class, 'getTypesPoisson']);
    Route::post('/referentiels/types-poisson', [ReferentielController::class, 'storeTypePoisson']);
    Route::delete('/referentiels/types-poisson/{id}', [ReferentielController::class, 'destroyTypePoisson']);
    Route::get('/referentiels/parametres', [ReferentielController::class, 'getParametres']);
    Route::post('/referentiels/parametres', [ReferentielController::class, 'updateParametre']);
    Route::get('/referentiels/pecheurs', [ReferentielController::class, 'getPecheurs']);
    Route::get('/referentiels/detaillants', [ReferentielController::class, 'getDetaillants']);

    Route::get('/stats', [StatsController::class, 'index']);

    Route::get('/export/excel', [ExportController::class, 'exportExcel']);
    Route::get('/export/pdf', [ExportController::class, 'exportPdf']);
});
