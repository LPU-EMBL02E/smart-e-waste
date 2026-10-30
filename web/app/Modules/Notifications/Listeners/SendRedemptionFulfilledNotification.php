<?php

namespace App\Modules\Notifications\Listeners;

use App\Modules\Notifications\Models\Notification;
use App\Modules\Notifications\Support\NotificationType;
use App\Modules\Rewards\Events\RedemptionFulfilled;

/**
 * RedemptionFulfilled -> one notification for the redeeming student.
 * API_Design.md §7.7, type REDEMPTION_FULFILLED.
 *
 * QUEUE_CONNECTION is `sync`, so this runs in the admin's request. A failure is
 * reported and swallowed: the redemption is already fulfilled.
 */
class SendRedemptionFulfilledNotification
{
    public function handle(RedemptionFulfilled $event): void
    {
        $redemption = $event->redemption;

        rescue(fn () => Notification::create([
            'user_id' => $redemption->user_id,
            'type' => NotificationType::RedemptionFulfilled,
            'title' => 'Reward handed over',
            'message' => sprintf(
                'Your redemption %s for %d × %s has been fulfilled.',
                $redemption->redemption_code,
                $redemption->quantity,
                $redemption->loadMissing('reward')->reward->name,
            ),
        ]));
    }
}
