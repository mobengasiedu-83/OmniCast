<?php

use App\Http\Controllers\Api\V1\AuthenticatedUserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:api'])
    ->prefix('v1')
    ->name('api.v1.')
    ->group(function (): void {
        Route::get('me', AuthenticatedUserController::class)->name('me');
    });
