<?php

namespace App\Modules\Identity\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * GET /profile and POST /profile/qr. API_Design.md §7.3.
 *
 * Shows the user's details, their organization, and their QR code rendered
 * SERVER-SIDE as SVG, so the page needs no JavaScript (P32).
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
 *
 * TODO: all method bodies.
 */
class ProfileController
{
    public function show(Request $request): View
    {
        // TODO
        throw new \LogicException('Not implemented.');
    }

    /**
     * Replace the QR token with a new one from QrToken::generate().
     */
    public function regenerateQr(Request $request): RedirectResponse
    {
        // TODO
        throw new \LogicException('Not implemented.');
    }
}
