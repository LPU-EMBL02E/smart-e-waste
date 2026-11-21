<?php

namespace App\Modules\Identity\Http\Controllers\Auth;

use App\Modules\Identity\Http\Requests\ChangeUnconfirmedEmailRequest;
use App\Modules\Identity\Services\AccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Email confirmation, through Laravel's built-in verification.
 * API_Design.md §7.2, P46, P48, System_Plan.md §4.
 *
 * The page, resending and correcting the email are the only signed-in pages an
 * unconfirmed user can open. The link itself needs no session (P57): Laravel's
 * `signed` middleware refuses a changed or expired link with a 403, and so does
 * verify() for a link that no longer matches an account.
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
     * GET /email/verify/{id}/{hash}, the link in the email. It confirms its
     * account on any device and never changes who is signed in (P57).
     */
    public function verify(Request $request, string $id, string $hash): RedirectResponse
    {
        $confirmed = $this->accounts->confirmEmailFromLink($id, $hash) ?? abort(403);
        $current = $request->user();

        if ($current === null) {
            return redirect()->route('login')->with('status', 'Your email is confirmed. Sign in to continue.');
        }

        if ($current->is($confirmed)) {
            // The signed-in copy predates the confirmation; keep it in step.
            Auth::setUser($confirmed);

            return redirect()->route($current->homeRouteName())->with('status', 'Your email is confirmed.');
        }

        // Someone else is signed in here. Their own unconfirmed account would
        // bounce /home to the confirmation page and lose the message.
        return redirect()
            ->route($current->hasVerifiedEmail() ? $current->homeRouteName() : 'verification.notice')
            ->with('status', "{$confirmed->email} is confirmed. You're still signed in as {$current->email}.");
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

    /**
     * PUT /email/verify. A student who registered with the wrong email fixes it
     * here, since the account already holds their student number and a second
     * registration would be refused.
     */
    public function changeEmail(ChangeUnconfirmedEmailRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->route($user->homeRouteName());
        }

        $status = $this->accounts->changeUnconfirmedEmail($user, $request->validated('email'))
            ? "Your email is now {$user->email}. A new link is on its way."
            : "Your email is now {$user->email}, but we couldn't send the link. Try \"Send the link again\" in a moment.";

        return redirect()->route('verification.notice')->with('status', $status);
    }
}
