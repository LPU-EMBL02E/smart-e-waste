<?php

namespace App\Modules\Notifications\Listeners;

use App\Modules\Deposits\Events\DepositCompleted;

/**
 * DepositCompleted -> one notification for the depositing student.
 * API_Design.md §7.7, type DEPOSIT_COMPLETED.
 *
 * The user comes from the transaction's session, because `transactions` has no
 * user column.
 *
 * QUEUE_CONNECTION is `sync`, so this runs during the request. Keep it cheap
 * and let it fail silently rather than breaking a deposit that has already been
 * credited.
 *
 * TODO: method body.
 */
class SendDepositCompletedNotification
{
    public function handle(DepositCompleted $event): void
    {
        throw new \LogicException('Not implemented.');
    }
}
