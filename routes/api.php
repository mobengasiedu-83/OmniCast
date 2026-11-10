<?php

use App\Http\Controllers\Api\V1\AuthenticatedUserController;
use App\Http\Controllers\Api\V1\CampaignController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Middleware\RequestLogContext;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Versioned HTTP API
|--------------------------------------------------------------------------
|
| Every route lives under `/api/v1` and is authorised by a Sanctum personal
| access token. The `read` and `write` abilities are what the token settings
| screen offers, so a reporting integration can be issued a read-only token that
| the API refuses to let create or launch anything.
|
*/

Route::get('v1/health', HealthController::class)
    ->middleware(RequestLogContext::class)
    ->name('api.v1.health');
Route::get('v1/health/ready', [HealthController::class, 'ready'])
    ->middleware(['throttle:api', RequestLogContext::class])
    ->name('api.v1.health.ready');

Route::middleware(['auth:sanctum', 'throttle:api', RequestLogContext::class])
    ->prefix('v1')
    ->name('api.v1.')
    ->group(function (): void {
        Route::get('me', AuthenticatedUserController::class)->name('me');

        Route::middleware('abilities:read')->group(function (): void {
            Route::get('campaigns', [CampaignController::class, 'index'])->name('campaigns.index');
            Route::get('campaigns/{campaign}', [CampaignController::class, 'show'])->name('campaigns.show');
        });
    });
