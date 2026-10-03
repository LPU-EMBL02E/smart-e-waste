<?php

namespace App\Modules\Identity\Http\Controllers\Auth;

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
 *
 * TODO: all method bodies.
 */
class NewPasswordController
{
    public function show(Request $request, string $token): View
    {
        // TODO: return view('identity.auth.reset-password', compact('token'))
        throw new \LogicException('Not implemented.');
    }

    public function store(Request $request): RedirectResponse
    {
        // TODO: Password::reset(...), min 8 characters, confirmed
        throw new \LogicException('Not implemented.');
    }
}
