<?php

/**
 * Deposits module — web routes. API_Design.md §7.3 and §7.4.
 */

use App\Modules\Deposits\Http\Controllers\Admin\SessionReviewController;
use App\Modules\Deposits\Http\Controllers\Admin\TransactionController as AdminTransactionController;
use App\Modules\Deposits\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

// ------------------------------------------------------------ signed in
Route::middleware('auth')->group(function () {
    // Own deposit history only (§7.1).
    Route::get('transactions', [TransactionController::class, 'index'])
        ->name('transactions.index');
});

// ----------------------------------------------------------------- admin
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('transactions', [AdminTransactionController::class, 'index'])
        ->name('transactions.index');

    Route::get('transactions/{transaction}', [AdminTransactionController::class, 'show'])
        ->name('transactions.show');

    Route::post('transactions/{transaction}/void', [AdminTransactionController::class, 'void'])
        ->name('transactions.void');

    // ACCEPTED sessions older than the review threshold.
    Route::get('sessions/review', [SessionReviewController::class, 'index'])
        ->name('sessions.review');
});
