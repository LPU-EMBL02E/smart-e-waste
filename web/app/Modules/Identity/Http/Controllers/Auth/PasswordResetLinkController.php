<?php

namespace App\Modules\Identity\Http\Controllers\Auth;

use App\Modules\Identity\Http\Requests\ForgotPasswordRequest;
use App\Modules\Identity\Services\AccountService;
use Illuminate\Http\RedirectResponse;
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
 * so the form cannot be used to find out who has one. That includes a failed
 * send: saying so here would reveal the account, so AccountService logs it and
 * the fallback is an admin setting the password.
 */
class PasswordResetLinkController
{
    public function __construct(private AccountService $accounts) {}

    public function show(): View
    {
        return view('identity.auth.forgot-password');
    }

    public function store(ForgotPasswordRequest $request): RedirectResponse
    {
        $this->accounts->sendPasswordLink($request->validated('email'));

        return back()->with('status', 'If that email has an account, a link to set a password has been sent.');
    }
}
