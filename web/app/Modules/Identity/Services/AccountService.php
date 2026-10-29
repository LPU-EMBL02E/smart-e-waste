<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\UserQrCredential;

/**
 * Account writes. System_Plan.md §4: Identity is the only writer of
 * `organizations`, `users` and `user_qr_credentials`, so other modules change
 * them only through this service.
 *
 * TODO: the account methods in web/docs/TRACK-A-ACCOUNTS.md step A2.
 */
class AccountService
{
    /**
     * Record that a QR token was just scanned at a bin. API_Design.md §5.4, P6.
     *
     * Called by DepositService inside the transaction that opens the session,
     * so the time is written only when the session is.
     */
    public function markQrCredentialUsed(UserQrCredential $credential): void
    {
        $credential->forceFill(['last_used_at' => now()])->save();
    }
}
