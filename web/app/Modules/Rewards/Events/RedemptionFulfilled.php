<?php

namespace App\Modules\Rewards\Events;

use App\Modules\Rewards\Models\Redemption;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An admin handed over a reward. API_Design.md §7.7.
 *
 * Dispatched by RedemptionService after fulfilment. Cancelling does NOT
 * dispatch an event: the points and stock are returned, and the plan defines no
 * notification for it.
 */
class RedemptionFulfilled
{
    use Dispatchable;

    public function __construct(public readonly Redemption $redemption) {}
}
