<?php

namespace App\Modules\Identity\Http\Controllers\Auth;

use App\Modules\Identity\Http\Requests\RegisterRequest;
use App\Modules\Identity\Services\AccountService;
use App\Modules\Identity\Services\OrganizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Self-registration — GET and POST /register. API_Design.md §7.2, §7.6, P46,
 * System_Plan.md §4.
 *
 * The student is signed in at once and sent to the page asking them to confirm
 * their email. If the confirmation email can't be sent, the account still stands
 * and that page says to use the resend button.
 */
class RegisterController
{
    public function __construct(
        private AccountService $accounts,
        private OrganizationService $organizations,
    ) {}

    public function show(): View
    {
        return view('identity.auth.register', [
            'organizations' => $this->organizations->activeOptions(),
        ]);
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $user = $this->accounts->register($request->validated());

        Auth::login($user);

        $status = $this->accounts->announceRegistration($user)
            ? "We sent a link to {$user->email}. Open it to finish setting up your account."
            : 'Your account was created, but we couldn\'t send the email. Use "Send the link again" in a moment.';

        return redirect()->route('verification.notice')->with('status', $status);
    }
}
