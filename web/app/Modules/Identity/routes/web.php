<?php

/**
 * Identity module — web routes. API_Design.md §7.2, §7.3 and §7.4.
 *
 * The four password routes keep Laravel's own route NAMES. `password.reset` in
 * particular must not be renamed: Laravel's reset notification builds its link
 * from that name, and both emails the system sends lead there.
 */

use App\Modules\Identity\Http\Controllers\Admin\OrganizationController;
use App\Modules\Identity\Http\Controllers\Admin\UserController;
use App\Modules\Identity\Http\Controllers\Auth\LoginController;
use App\Modules\Identity\Http\Controllers\Auth\NewPasswordController;
use App\Modules\Identity\Http\Controllers\Auth\PasswordResetLinkController;
use App\Modules\Identity\Http\Controllers\ProfileController;
use App\Modules\Identity\Http\Controllers\RootController;
use Illuminate\Support\Facades\Route;

// ----------------------------------------------------------------- guest
Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::post('login', [LoginController::class, 'store']);

    Route::get('forgot-password', [PasswordResetLinkController::class, 'show'])
        ->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    // Both the set-password email for a new account and the forgot-password
    // email land here.
    Route::get('reset-password/{token}', [NewPasswordController::class, 'show'])
        ->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.update');
});

// ------------------------------------------------------------ signed in
Route::middleware('auth')->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    // Redirects by role: /admin for an admin, /home for a student.
    Route::get('/', RootController::class)->name('root');

    Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::post('profile/qr', [ProfileController::class, 'regenerateQr'])
        ->name('profile.qr.regenerate');
});

// ----------------------------------------------------------------- admin
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    // No destroy routes anywhere: users and organizations are switched off with
    // their status / is_active column, because other tables reference them (P33).
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::get('users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('users', [UserController::class, 'store'])->name('users.store');
    Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::post('users/import', [UserController::class, 'import'])->name('users.import');
    Route::post('users/{user}/password-link', [UserController::class, 'sendPasswordLink'])
        ->name('users.password-link');
    Route::put('users/{user}/password', [UserController::class, 'setPassword'])
        ->name('users.password');

    Route::get('organizations', [OrganizationController::class, 'index'])
        ->name('organizations.index');
    Route::get('organizations/create', [OrganizationController::class, 'create'])
        ->name('organizations.create');
    Route::post('organizations', [OrganizationController::class, 'store'])
        ->name('organizations.store');
    Route::get('organizations/{organization}/edit', [OrganizationController::class, 'edit'])
        ->name('organizations.edit');
    Route::put('organizations/{organization}', [OrganizationController::class, 'update'])
        ->name('organizations.update');
});
