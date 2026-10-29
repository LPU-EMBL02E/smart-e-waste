<?php

namespace App\Modules\Notifications\Listeners;

use App\Modules\Deposits\Events\DepositCompleted;
use App\Modules\Notifications\Models\Notification;
use App\Modules\Notifications\Support\NotificationType;

/**
 * DepositCompleted -> one notification for the depositing student.
 * API_Design.md §7.7, type DEPOSIT_COMPLETED.
 *
 * The user comes from the transaction's session, because `transactions` has no
 * user column.
 *
 * QUEUE_CONNECTION is `sync`, so this runs during the request. Keep it cheap
 * and let it fail silently rather than breaking a deposit that has already been
 * credited: a failure is reported and swallowed.
 */
class SendDepositCompletedNotification
{
    public function handle(DepositCompleted $event): void
    {
        $transaction = $event->transaction;

        rescue(fn () => Notification::create([
            'user_id' => $transaction->session->user_id,
            'type' => NotificationType::DepositCompleted,
            'title' => 'Points added',
            'message' => sprintf(
                'You earned %d points for deposit %s.',
                $transaction->points_awarded,
                $transaction->transaction_code,
            ),
        ]));
    }
}
