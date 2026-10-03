<?php

namespace App\Modules\Identity\Http\Controllers\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Forgot password" — GET and POST /forgot-password, route names
 * password.request and password.email. API_Design.md §7.2.
 *
 * Uses Laravel's password broker, the same one behind the set-password email
 * sent to a new account; both lead to /reset-password/{token}
 * (System_Plan.md §7.2).
 *
 * The reply is the SAME whether or not the email belongs to an account (P30),
 * so the form cannot be used to find out who has one.
 *
 * Mail is sent during the request — there is no queue. A failed send must not
 * fail the action: catch it and fall back to an admin setting the password.
 *
 * TODO: all method bodies.
 */
class PasswordResetLinkController
{
    public function show(): View
    {
        // TODO: return view('identity.auth.forgot-password')
        throw new \LogicException('Not implemented.');
    }

    public function store(Request $request): RedirectResponse
    {
        // TODO: Password::sendResetLink($request->only('email'))
        throw new \LogicException('Not implemented.');
    }
}
