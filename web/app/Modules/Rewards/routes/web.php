<?php

/**
 * Rewards module — web routes. API_Design.md §7.3 and §7.4.
 */

use App\Modules\Rewards\Http\Controllers\Admin\PointsRateController;
use App\Modules\Rewards\Http\Controllers\Admin\RedemptionController as AdminRedemptionController;
use App\Modules\Rewards\Http\Controllers\Admin\RewardController as AdminRewardController;
use App\Modules\Rewards\Http\Controllers\HomeController;
use App\Modules\Rewards\Http\Controllers\RedemptionController;
use App\Modules\Rewards\Http\Controllers\RewardController;
use Illuminate\Support\Facades\Route;

// ------------------------------------------------------------ signed in
Route::middleware('auth')->group(function () {
    Route::get('home', HomeController::class)->name('home');

    Route::get('rewards', [RewardController::class, 'index'])->name('rewards.index');
    Route::get('rewards/{reward}', [RewardController::class, 'show'])->name('rewards.show');

    // Redeeming needs the website login. A QR scan at the bin can never spend
    // points (System_Plan.md §5.5).
    Route::post('redemptions', [RedemptionController::class, 'store'])
        ->name('redemptions.store');
    Route::get('redemptions', [RedemptionController::class, 'index'])
        ->name('redemptions.index');
});

// ----------------------------------------------------------------- admin
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('rewards', [AdminRewardController::class, 'index'])->name('rewards.index');
    Route::get('rewards/create', [AdminRewardController::class, 'create'])->name('rewards.create');
    Route::post('rewards', [AdminRewardController::class, 'store'])->name('rewards.store');
    Route::get('rewards/{reward}/edit', [AdminRewardController::class, 'edit'])->name('rewards.edit');
    Route::put('rewards/{reward}', [AdminRewardController::class, 'update'])->name('rewards.update');

    Route::get('redemptions', [AdminRedemptionController::class, 'index'])
        ->name('redemptions.index');
    Route::post('redemptions/{redemption}/fulfil', [AdminRedemptionController::class, 'fulfil'])
        ->name('redemptions.fulfil');
    Route::post('redemptions/{redemption}/cancel', [AdminRedemptionController::class, 'cancel'])
        ->name('redemptions.cancel');

    // No edit route: saving closes the current rule and inserts a new one.
    Route::get('points-rate', [PointsRateController::class, 'show'])->name('points-rate.show');
    Route::post('points-rate', [PointsRateController::class, 'store'])->name('points-rate.store');
});
