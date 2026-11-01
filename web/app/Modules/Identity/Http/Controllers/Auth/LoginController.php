<?php

namespace App\Modules\Identity\Http\Controllers\Auth;

use App\Modules\Identity\Http\Requests\LoginRequest;
use App\Modules\Identity\Services\AccountService;
use App\Modules\Identity\Support\LoginRefused;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Email-and-password login. API_Design.md §7.2, P29.
 *
 * Uses Laravel's own auth guard; this module writes no authentication logic of
 * its own (System_Plan.md §4). Keeping users keyed by email is what lets the
 * school add single sign-on later with Socialite and one controller
 * (System_Plan.md §8.1).
 *
 * Login must fail for:
 *   - an account whose password is NULL (never activated), and
 *   - a user whose status is inactive,
 * both with the SAME message as a wrong password, so neither state can be
 * probed from the login form.
 *
 * Rate limit: 5 attempts a minute per email-and-IP pair. A success sets
 * users.last_login_at (P6) and redirects an admin to /admin and a student to
 * /home. AccountService::signIn() holds these rules.
 */
class LoginController
{
    public function __construct(private AccountService $accounts) {}

    public function show(): View
    {
        return view('identity.auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        try {
            $user = $this->accounts->signIn(
                $request->validated('email'),
                $request->validated('password'),
                $request->throttleKey(),
            );
        } catch (LoginRefused $refused) {
            return back()->withErrors(['email' => $refused->getMessage()])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->route($user->homeRouteName());
    }

    /**
     * Sign out, invalidate the session and regenerate the CSRF token.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
