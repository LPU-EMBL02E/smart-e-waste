<?php

/**
 * Deposits module — device API routes. API_Design.md §5.4–§5.7.
 *
 * The four calls that make up one deposit, in the order the bin makes them.
 * Sessions are addressed by UUID. The controller scopes every lookup to the
 * calling device, so one bin can never act on another bin's session.
 */

use App\Modules\Deposits\Http\Controllers\Device\SessionController;
use Illuminate\Support\Facades\Route;

Route::post('sessions', [SessionController::class, 'store'])
    ->name('device.sessions.store');

Route::prefix('sessions/{session}')->group(function () {
    Route::post('deposit', [SessionController::class, 'deposit'])
        ->name('device.sessions.deposit');

    Route::post('complete', [SessionController::class, 'complete'])
        ->name('device.sessions.complete');

    Route::post('cancel', [SessionController::class, 'cancel'])
        ->name('device.sessions.cancel');
});
