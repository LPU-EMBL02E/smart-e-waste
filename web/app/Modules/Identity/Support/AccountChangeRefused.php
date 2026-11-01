<?php

namespace App\Modules\Identity\Support;

use RuntimeException;

/**
 * An account change the rules refuse. web/docs/ACCOUNT-MANAGEMENT.md ("Prevent
 * admin lockout").
 *
 * Thrown inside AccountService's database transaction, so nothing changes. The
 * controller redirects back with the message (API_Design.md §7.1).
 */
final class AccountChangeRefused extends RuntimeException
{
    public static function lastAdmin(): self
    {
        return new self('This is the last active admin with a password. Make another admin first.');
    }
}
