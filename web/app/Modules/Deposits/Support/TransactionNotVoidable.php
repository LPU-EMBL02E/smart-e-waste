<?php

namespace App\Modules\Deposits\Support;

use RuntimeException;

/**
 * A void the rules refuse: only a COMPLETED transaction can be voided.
 * API_Design.md §7.6 ("Void a transaction"), P24.
 *
 * Thrown inside DepositService's database transaction, so nothing changes. The
 * controller redirects back with the message (API_Design.md §7.1).
 */
final class TransactionNotVoidable extends RuntimeException
{
    public static function notCompleted(string $status): self
    {
        return new self('Only a completed transaction can be voided. This one is '.strtolower($status).'.');
    }
}
