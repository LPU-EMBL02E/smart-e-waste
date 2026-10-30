<?php

namespace App\Modules\Rewards\Support;

use RuntimeException;

/**
 * A redemption, fulfilment or cancellation the rules refuse.
 * API_Design.md §7.6, System_Plan.md §6.1.
 *
 * Thrown inside RedemptionService's database transaction, so the refusal rolls
 * everything back and changes nothing. The controller redirects back with the
 * message (API_Design.md §7.1), which is written for the person on the page.
 */
final class RedemptionRefused extends RuntimeException
{
    public static function rewardUnavailable(): self
    {
        return new self('This reward is no longer available.');
    }

    public static function insufficientBalance(int $balance, int $cost): self
    {
        return new self("You need {$cost} points for this and have {$balance}.");
    }

    public static function outOfStock(): self
    {
        return new self('There is not enough of this reward left in stock.');
    }

    public static function notPending(string $status): self
    {
        return new self('Only a pending redemption can be changed. This one is '.strtolower($status).'.');
    }
}
