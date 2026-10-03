<?php

/**
 * Devices module — web routes. API_Design.md §7.4.
 *
 * Admin-only: students never see bin internals. "Bins" in the URL is the
 * admin-facing name for a device row.
 */

use App\Modules\Devices\Http\Controllers\Admin\BinController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('bins', [BinController::class, 'index'])->name('bins.index');
    Route::get('bins/create', [BinController::class, 'create'])->name('bins.create');
    Route::post('bins', [BinController::class, 'store'])->name('bins.store');
    Route::get('bins/{device}', [BinController::class, 'show'])->name('bins.show');
    Route::get('bins/{device}/edit', [BinController::class, 'edit'])->name('bins.edit');
    Route::put('bins/{device}', [BinController::class, 'update'])->name('bins.update');

    // Issues a new secret and shows it once. Stops the old one immediately.
    Route::post('bins/{device}/secret', [BinController::class, 'rotateSecret'])
        ->name('bins.secret');
});
