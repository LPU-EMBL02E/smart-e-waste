<?php

/**
 * Reporting module — web routes. API_Design.md §7.3, §7.4 and §7.5.
 *
 * Read-only. This module owns no tables and writes nothing.
 */

use App\Modules\Reporting\Http\Controllers\Admin\DashboardController;
use App\Modules\Reporting\Http\Controllers\Admin\OrganizationLeaderboardController;
use App\Modules\Reporting\Http\Controllers\Admin\ReportController;
use App\Modules\Reporting\Http\Controllers\LeaderboardController;
use Illuminate\Support\Facades\Route;

// ------------------------------------------------------------ signed in
Route::middleware('auth')->group(function () {
    // Ranked by points earned, not balance. Includes admins.
    Route::get('leaderboard', LeaderboardController::class)->name('leaderboard');
});

// ----------------------------------------------------------------- admin
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // The one JSON web route. Normal session, not the device signature scheme.
    Route::get('dashboard/summary', [DashboardController::class, 'summary'])
        ->name('dashboard.summary');

    // Students only, grouped by organization.
    Route::get('leaderboard/organizations', OrganizationLeaderboardController::class)
        ->name('leaderboard.organizations');

    Route::get('reports', ReportController::class)->name('reports');
});
