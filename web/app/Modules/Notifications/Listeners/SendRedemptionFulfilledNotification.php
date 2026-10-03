<?php

namespace App\Modules\Notifications\Listeners;

use App\Modules\Rewards\Events\RedemptionFulfilled;

/**
 * RedemptionFulfilled -> one notification for the redeeming student.
 * API_Design.md §7.7, type REDEMPTION_FULFILLED.
 *
 * TODO: method body.
 */
class SendRedemptionFulfilledNotification
{
    public function handle(RedemptionFulfilled $event): void
    {
        throw new \LogicException('Not implemented.');
    }
}
