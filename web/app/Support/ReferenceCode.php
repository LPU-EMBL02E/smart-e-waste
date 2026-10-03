<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * The human-readable codes on transactions and redemptions. API_Design.md P25.
 *
 *   TXN-20261003-7K2QX9
 *   RDM-20261003-F3M8TB
 *
 * Prefix, the date as YYYYMMDD, then six random upper-case letters and digits.
 * The date part uses the display time zone, so a code reads the way the day did
 * locally. Collisions are left to the unique index to catch: on a duplicate-key
 * error the caller generates another code and retries.
 */
final class ReferenceCode
{
    public static function transaction(): string
    {
        return self::make(config('ewaste.codes.transaction_prefix'));
    }

    public static function redemption(): string
    {
        return self::make(config('ewaste.codes.redemption_prefix'));
    }

    private static function make(string $prefix): string
    {
        $date = now(config('ewaste.display_timezone'))->format('Ymd');
        $random = Str::upper(Str::random(config('ewaste.codes.random_length')));

        return "{$prefix}-{$date}-{$random}";
    }
}
