<?php

namespace App\Modules\Deposits\Events;

use App\Modules\Deposits\Models\Transaction;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A deposit completed and its points were credited. API_Design.md §7.7.
 *
 * Dispatched by DepositService AFTER the database transaction commits, never
 * inside it: a listener must not be able to roll back a credited deposit, and
 * must not run for a transaction that was rolled back.
 *
 * Deposits announces; it never calls Notifications
 * (System_Plan.md §4, boundary rule 3). Adding email or SMS later is one more
 * listener, with no module changed (System_Plan.md §8.1).
 */
class DepositCompleted
{
    use Dispatchable;

    public function __construct(public readonly Transaction $transaction) {}
}
