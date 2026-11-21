<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Http\Requests\UpdatePasswordRequest;
use App\Modules\Identity\Http\Requests\UpdateProfileRequest;
use App\Modules\Identity\Services\AccountService;
use App\Modules\Identity\Services\OrganizationService;
use App\Modules\Identity\Support\QrCodeImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * GET /profile, POST /profile/qr, PUT /profile and PUT /profile/password.
 * API_Design.md §7.3, §7.6 ("Change own details", "Change own password"), P50.
 *
 * Shows the user's details, their organization, and their QR token. The token is
 * text for now (web/docs/TRACK-A-ACCOUNTS.md step A7). The design is the QR code
 * rendered SERVER-SIDE as SVG, so the page needs no JavaScript (P32), which needs
 * the package choice below.
 *
 * Regenerating overwrites the user_qr_credentials row, so the old token stops
 * working at once. A student whose printed QR is lost or copied gets a new one
 * from here.
 *
 * TODO(team): API_Design.md §10 question 8 — which Composer package renders the
 * QR code, and whether it supports Laravel 13. Pick one before building the
 * view, and keep it behind this controller so it can be replaced.
 */
class ProfileController
{
    public function __construct(
        private AccountService $accounts,
        private OrganizationService $organizations,
    ) {}

    public function show(Request $request): View
    {
        $user = $this->accounts->loadProfile($request->user());

        return view('identity.profile.show', [
            'user' => $user,
            'qrSvg' => QrCodeImage::svg($user->qrCredential->token),
            // A student may stay in an organization that has been switched off.
            'organizations' => $user->isAdmin() ? collect() : $this->organizations->activeOptions($user->organization_id),
        ]);
    }

    /**
     * Replace the QR token with a new one from QrToken::generate().
     */
    public function regenerateQr(Request $request): RedirectResponse
    {
        $this->accounts->regenerateQr($request->user());

        return redirect()->route('profile.show')
            ->with('status', 'Your QR code has been replaced. Your old code no longer works.');
    }

    /**
     * A new email has to be confirmed before anything else, so the user goes to
     * the confirmation page, where a mistyped address can be corrected.
     */
    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $sent = $this->accounts->updateOwnDetails(
            $user,
            $request->safe()->except('current_password'),
            $request->session()->getId(),
        );

        if ($sent === null) {
            return redirect()->route('profile.show')->with('status', 'Your details have been saved.');
        }

        return redirect()->route('verification.notice')->with('status', $sent
            ? "Your details have been saved. Confirm your new email: a link is on its way to {$user->email}."
            : 'Your details have been saved, but we couldn\'t send the confirmation email. Try "Send the link again" in a moment.');
    }

    /**
     * The new session id stops the old one, which other sessions may have seen
     * in a shared browser, from being reused.
     */
    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $this->accounts->changeOwnPassword(
            $request->user(),
            $request->validated('password'),
            $request->session()->getId(),
        );

        $request->session()->regenerate();

        return redirect()->route('profile.show')
            ->with('status', 'Your password has been changed. You have been signed out everywhere else.');
    }
}
