<?php

namespace App\Modules\Rewards\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Rewards\Models\Redemption;
use App\Modules\Rewards\Models\Reward;

/**
 * Redeeming, fulfilling and cancelling rewards. API_Design.md §7.6,
 * System_Plan.md §6.1.
 *
 * Two concurrency rules that the whole design rests on:
 *   - the user's row is locked with SELECT ... FOR UPDATE before the balance is
 *     summed, or two simultaneous redemptions can overdraw;
 *   - stock is decremented with a guarded `WHERE stock_quantity >= ?` and the
 *     affected row count is checked, so stock can never go negative.
 *
 * TODO: all method bodies.
 */
class RedemptionService
{
    public function __construct(private PointsService $points)
    {
    }

    /**
     * Redeem a reward for a user. One database transaction:
     *
     *   1. Lock the user's row (SELECT ... FOR UPDATE), then sum the ledger.
     *   2. Refuse if the balance is below points_cost × quantity.
     *   3. Decrement stock with WHERE stock_quantity >= ? and check the affected
     *      row count; refuse if no row changed.
     *   4. Insert the redemption: ReferenceCode::redemption(), points_cost = the
     *      TOTAL cost, status = PENDING.
     *   5. PointsService writes the REDEEM row with a negative delta.
     *
     * A refusal changes nothing and the page redirects back with a message.
     */
    public function redeem(User $user, Reward $reward, int $quantity): Redemption
    {
        // TODO
        throw new \LogicException('Not implemented.');
    }

    /**
     * Mark a redemption fulfilled. Only while PENDING. Sets status and
     * fulfilled_at, then dispatches RedemptionFulfilled, whose listener writes
     * the student's notification.
     */
    public function fulfil(Redemption $redemption): Redemption
    {
        // TODO
        throw new \LogicException('Not implemented.');
    }

    /**
     * Cancel a PENDING redemption. One database transaction: status becomes
     * CANCELLED, the stock is returned, and PointsService writes a REVERSAL row
     * giving the points back.
     *
     * A FULFILLED redemption cannot be cancelled (System_Plan.md §6.1).
     */
    public function cancel(Redemption $redemption, string $reason): Redemption
    {
        // TODO
        throw new \LogicException('Not implemented.');
    }
}
