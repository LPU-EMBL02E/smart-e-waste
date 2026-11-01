<?php

namespace App\Modules\Identity\Http\Controllers\Auth;

use App\Modules\Identity\Services\AccountService;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Email confirmation, through Laravel's built-in verification.
 * API_Design.md §7.2, P46, P48, System_Plan.md §4.
 *
 * These three routes are the only signed-in pages an unconfirmed user can open.
 * Laravel's `signed` middleware and EmailVerificationRequest refuse a changed,
 * expired or someone else's link with a 403.
 */
class EmailVerificationController
{
    public function __construct(private AccountService $accounts) {}

    /**
     * GET /email/verify. Someone already confirmed has nothing to do here.
     */
    public function notice(Request $request): View|RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route($request->user()->homeRouteName());
        }

        return view('identity.auth.verify-email');
    }

    /**
     * GET /email/verify/{id}/{hash}, the link in the email.
     */
    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        $request->fulfill();

        return redirect()->route('home')->with('status', 'Your email is confirmed.');
    }

    /**
     * POST /email/verification-notification. The user is signed in and this is
     * their own address, so unlike /forgot-password a failed send can be reported.
     */
    public function send(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->route($user->homeRouteName());
        }

        $status = $this->accounts->sendConfirmationEmail($user)
            ? "A new link is on its way to {$user->email}."
            : 'We couldn\'t send the email. Try "Send the link again" in a moment.';

        return redirect()->route('verification.notice')->with('status', $status);
    }
}
