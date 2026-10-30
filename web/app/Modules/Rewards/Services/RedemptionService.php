<?php

namespace App\Modules\Rewards\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Rewards\Models\Redemption;
use App\Modules\Rewards\Models\Reward;
use App\Modules\Rewards\Support\RedemptionRefused;
use App\Support\ReferenceCode;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

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
 * Only PointsService writes point_ledger; this service calls it inside its own
 * transaction (System_Plan.md §4, boundary rule 1). A refusal is a
 * RedemptionRefused thrown inside the transaction, so it changes nothing.
 *
 * TODO: fulfil() and cancel().
 */
class RedemptionService
{
    public function __construct(private PointsService $points) {}

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
     * A refusal throws RedemptionRefused inside the transaction, so it changes
     * nothing; the page redirects back with its message. The balance and the
     * stock are checked here, never in validation, where the check would race.
     */
    public function redeem(User $user, Reward $reward, int $quantity): Redemption
    {
        return DB::transaction(function () use ($user, $reward, $quantity) {
            // The lock is the first read, so the balance below is current and a
            // second redemption by this user waits for this one (Section 4.1).
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $balance = $this->points->balanceFor($user);

            // Re-read after the lock: the price and is_active as they are now.
            $reward = Reward::whereKey($reward->id)->firstOrFail();

            if (! $reward->is_active) {
                throw RedemptionRefused::rewardUnavailable();
            }

            $cost = $reward->points_cost * $quantity;

            if ($balance < $cost) {
                throw RedemptionRefused::insufficientBalance($balance, $cost);
            }

            $taken = Reward::whereKey($reward->id)
                ->where('stock_quantity', '>=', $quantity)
                ->decrement('stock_quantity', $quantity);

            if ($taken === 0) {
                throw RedemptionRefused::outOfStock();
            }

            $redemption = ReferenceCode::insertWithRetry(fn () => Redemption::create([
                'redemption_code' => ReferenceCode::redemption(),
                'user_id' => $user->id,
                'reward_id' => $reward->id,
                'quantity' => $quantity,
                'points_cost' => $cost,
                'status' => Redemption::STATUS_PENDING,
            ]));

            $this->points->debitRedemption($redemption);

            return $redemption->setRelation('reward', $reward);
        });
    }

    /**
     * A user's own redemptions, newest first, with the reward eager-loaded
     * (API_Design.md §7.1: a user sees only their own).
     *
     * @return LengthAwarePaginator<int, Redemption>
     */
    public function listFor(User $user): LengthAwarePaginator
    {
        return Redemption::where('user_id', $user->id)
            ->with('reward')
            ->latest('id')
            ->paginate();
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
