<?php

namespace App\Modules\Identity\Http\Controllers\Auth;

use App\Modules\Identity\Http\Requests\ResetPasswordRequest;
use App\Modules\Identity\Services\AccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Setting a password from an emailed link — GET /reset-password/{token}
 * (route name password.reset) and POST /reset-password (password.update).
 * API_Design.md §7.2.
 *
 * Keep the name `password.reset`: Laravel's reset notification builds its link
 * from that route name, so renaming it silently breaks both emails.
 *
 * This is also how a NEW account is activated. Until it succeeds, users.password
 * is NULL and the account cannot sign in. No default password is ever assigned
 * or emailed (System_Plan.md §4).
 *
 * Link lifetime is Laravel's `passwords.users.expire` in config/auth.php.
 * TODO(team): API_Design.md §10 question 4 — Laravel's documented default is 60
 * minutes. Decide whether that is long enough for a new account's set-password
 * email, given a student who misses it has to use "Forgot password".
 */
class NewPasswordController
{
    public function __construct(private AccountService $accounts) {}

    public function show(Request $request, string $token): View
    {
        return view('identity.auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function store(ResetPasswordRequest $request): RedirectResponse
    {
        $reset = $this->accounts->resetPassword(
            $request->validated('email'),
            $request->validated('token'),
            $request->validated('password'),
        );

        if (! $reset) {
            return back()
                ->withErrors(['email' => 'This link is not valid or has expired. Ask for a new one.'])
                ->onlyInput('email');
        }

        return redirect()->route('login')->with('status', 'Your password has been set. You can sign in now.');
    }
}
