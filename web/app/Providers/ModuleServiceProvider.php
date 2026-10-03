<?php

namespace App\Providers;

use App\Modules\Deposits\Events\DepositCompleted;
use App\Modules\Devices\Events\BinFull;
use App\Modules\Notifications\Listeners\SendBinFullNotifications;
use App\Modules\Notifications\Listeners\SendDepositCompletedNotification;
use App\Modules\Notifications\Listeners\SendRedemptionFulfilledNotification;
use App\Modules\Rewards\Events\RedemptionFulfilled;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Loads the modular monolith. System_Plan.md §4.
 *
 * One Laravel app, one database, five domain modules plus Notifications. This
 * provider is the only place that knows the module list, so adding a module is
 * one line here.
 *
 * It does two things:
 *   1. loads each module's routes — web routes in the `web` middleware group,
 *      device routes under /api/v1/device with the signature middleware;
 *   2. wires the three events to their listeners, which is the whole of the
 *      Notifications module's coupling to the rest of the app.
 *
 * Register it in bootstrap/providers.php (see SETUP.md).
 */
class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Modules with a routes/ directory, in load order.
     */
    private const MODULES = [
        'Identity',
        'Devices',
        'Deposits',
        'Rewards',
        'Reporting',
        'Notifications',
    ];

    /**
     * Events -> listeners. API_Design.md §7.7.
     *
     * Modules announce; they never call Notifications
     * (System_Plan.md §4, boundary rule 3). Adding email or SMS later means
     * adding a listener here and changing no module.
     */
    private const LISTENERS = [
        DepositCompleted::class => [SendDepositCompletedNotification::class],
        RedemptionFulfilled::class => [SendRedemptionFulfilledNotification::class],
        BinFull::class => [SendBinFullNotifications::class],
    ];

    public function boot(): void
    {
        $this->loadModuleRoutes();
        $this->registerListeners();
    }

    private function loadModuleRoutes(): void
    {
        foreach (self::MODULES as $module) {
            $base = app_path("Modules/{$module}/routes");

            // Student and admin pages: session cookie and CSRF token.
            if (is_file("{$base}/web.php")) {
                Route::middleware('web')->group("{$base}/web.php");
            }

            // The bin's JSON API: stateless, HMAC-signed, no session and no CSRF.
            // GET /device/time opts out of the signature with
            // ->withoutMiddleware('device.signed').
            if (is_file("{$base}/device.php")) {
                Route::prefix('api/v1/device')
                    ->middleware('device.signed')
                    ->group("{$base}/device.php");
            }
        }
    }

    private function registerListeners(): void
    {
        foreach (self::LISTENERS as $event => $listeners) {
            foreach ($listeners as $listener) {
                Event::listen($event, $listener);
            }
        }
    }
}
