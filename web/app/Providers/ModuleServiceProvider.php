<?php

namespace App\Providers;

use App\Modules\Deposits\Events\DepositCompleted;
use App\Modules\Devices\Events\BinFault;
use App\Modules\Devices\Events\BinFull;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Support\Theme;
use App\Modules\Notifications\Listeners\SendBinFaultNotifications;
use App\Modules\Notifications\Listeners\SendBinFullNotifications;
use App\Modules\Notifications\Listeners\SendDepositCompletedNotification;
use App\Modules\Notifications\Listeners\SendRedemptionFulfilledNotification;
use App\Modules\Rewards\Events\RedemptionFulfilled;
use App\Support\Display;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Loads the modular monolith. System_Plan.md §4.
 *
 * One Laravel app, one database, five domain modules plus Notifications. This
 * provider is the only place that knows the module list, so adding a module is
 * one line here.
 *
 * Mainly it does two things:
 *   1. loads each module's routes — web routes in the `web` middleware group,
 *      device routes under /api/v1/device with the signature middleware;
 *   2. wires the module events to their listeners, which is the whole of the
 *      Notifications module's coupling to the rest of the app.
 * It also shares a few values with the layouts and adds the deadline to the
 * confirmation email.
 *
 * Registered in bootstrap/providers.php.
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
        BinFault::class => [SendBinFaultNotifications::class],
    ];

    public function boot(): void
    {
        $this->loadModuleRoutes();
        $this->registerListeners();
        $this->shareUnreadNotificationCount();
        $this->shareTheme();
        $this->addDeadlineToConfirmationEmail();
    }

    /**
     * Laravel's confirmation email, plus the date after which an account never
     * confirmed is deleted (API_Design.md §7.2, P54). An email confirming a
     * changed address has no deadline, so it is Laravel's email unchanged.
     */
    private function addDeadlineToConfirmationEmail(): void
    {
        VerifyEmail::toMailUsing(function (User $user, string $url): MailMessage {
            $message = (new MailMessage)
                ->subject(Lang::get('Verify your email address'))
                ->line(Lang::get('Please click the button below to verify your email address.'))
                ->action(Lang::get('Verify Email Address'), $url);

            if ($deadline = $user->confirmationDeadline()) {
                $message->line('Confirm it by '.Display::dateTime($deadline).' ('.config('ewaste.display_timezone')
                    .'). After that, the account is deleted and the email address can be used to sign up again.');
            }

            return $message->line(Lang::get('If you did not create an account, no further action is required.'));
        });
    }

    /**
     * The visitor's theme, read from their cookie, for the <html> element and
     * the theme control (API_Design.md P52). No query.
     */
    private function shareTheme(): void
    {
        View::composer(['layouts.base', 'layouts.partials.theme'], function ($view): void {
            $view->with('theme', Theme::fromCookie(request()->cookie(Theme::COOKIE)));
        });
    }

    /**
     * The sidebar's unread count, for the signed-in user. One count on the
     * (user_id, read_at) index, only when a page draws the sidebar: never on a
     * redirect, a device route or an error page.
     */
    private function shareUnreadNotificationCount(): void
    {
        View::composer('layouts.partials.sidebar', function ($view): void {
            $view->with('unreadNotifications', auth()->user()->notifications()->unread()->count());
        });
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
