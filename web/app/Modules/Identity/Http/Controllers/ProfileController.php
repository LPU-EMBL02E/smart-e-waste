<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Services\AccountService;
use App\Modules\Identity\Support\QrCodeImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * GET /profile and POST /profile/qr. API_Design.md §7.3.
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
 *
 * TODO(team): API_Design.md §10 question 3 — no route exists for a user
 * changing their own password or details. Decide whether that is in scope.
 */
class ProfileController
{
    public function __construct(private AccountService $accounts) {}

    public function show(Request $request): View
    {
        $user = $this->accounts->loadProfile($request->user());

        return view('identity.profile.show', [
            'user' => $user,
            'qrSvg' => QrCodeImage::svg($user->qrCredential->token),
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
}
