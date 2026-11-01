<?php

namespace App\Modules\Identity\Http\Controllers\Auth;

use Illuminate\View\View;

/**
 * Email confirmation. API_Design.md §7.2, P48.
 *
 * Laravel's `verified` middleware redirects an unconfirmed user to the
 * `verification.notice` route by name, so that route exists from the moment the
 * gate does (web/docs/TRACK-A-ACCOUNTS.md step A1). No unconfirmed account can
 * exist until registration lands, so nobody reaches this stub.
 *
 * TODO: the notice page, verify() and send() in step A4.
 */
class EmailVerificationController
{
    public function notice(): View
    {
        // TODO: return view('identity.auth.verify-email')
        throw new \LogicException('Not implemented.');
    }
}
