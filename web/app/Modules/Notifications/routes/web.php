<?php

/**
 * Notifications module — web routes. API_Design.md §7.3.
 *
 * One route for both roles: admins read their bin-full alerts at the same path
 * students use (§7.4).
 */

use App\Modules\Notifications\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('notifications', [NotificationController::class, 'index'])
        ->name('notifications.index');

    Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead'])
        ->name('notifications.read');
});
