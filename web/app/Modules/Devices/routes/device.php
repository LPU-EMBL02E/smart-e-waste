<?php

/**
 * Devices module — device API routes. API_Design.md §5.1–§5.3.
 *
 * Loaded by ModuleServiceProvider under the /api/v1/device prefix, OUTSIDE the
 * web middleware group: no session, no CSRF.
 *
 * Every route here is signed except GET /time, which is listed first and
 * deliberately excluded from the signature middleware.
 */

use App\Modules\Devices\Http\Controllers\Device\ConfigController;
use App\Modules\Devices\Http\Controllers\Device\TelemetryController;
use App\Modules\Devices\Http\Controllers\Device\TimeController;
use Illuminate\Support\Facades\Route;

// UNSIGNED. The ESP32 has no clock and cannot sign anything until it has the
// time, so this route cannot require a signature (API_Design.md §3.2).
Route::get('time', TimeController::class)
    ->withoutMiddleware('device.signed')
    ->name('device.time');

Route::get('config', ConfigController::class)->name('device.config');
Route::post('telemetry', TelemetryController::class)->name('device.telemetry');
