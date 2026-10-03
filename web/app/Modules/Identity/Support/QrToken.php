<?php

namespace App\Modules\Identity\Support;

use Illuminate\Support\Str;

/**
 * Generates the opaque token a student's QR code encodes. API_Design.md P35.
 *
 * 40 random letters and digits. It is deliberately NOT the student number,
 * which would be guessable and forgeable (System_Plan.md §5.5), and it is not
 * derived from anything about the user.
 *
 * Regenerating overwrites user_qr_credentials, so the previous token stops
 * matching immediately.
 */
final class QrToken
{
    public static function generate(): string
    {
        return Str::random(config('ewaste.qr_token_length'));
    }
}
