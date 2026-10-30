<?php

namespace App\Modules\Rewards\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Rewards\Events\RedemptionFulfilled;
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
     * Mark a redemption fulfilled: the admin handed the reward over.
     * API_Design.md §7.6 ("Fulfil or cancel a redemption"), P24.
     *
     * Only while PENDING. Sets status and fulfilled_at, then dispatches
     * RedemptionFulfilled AFTER the commit; its listener writes the student's
     * notification.
     */
    public function fulfil(Redemption $redemption): Redemption
    {
        $fulfilled = DB::transaction(function () use ($redemption) {
            $redemption = $this->lockPending($redemption);

            $redemption->forceFill([
                'status' => Redemption::STATUS_FULFILLED,
                'fulfilled_at' => now(),
            ])->save();

            return $redemption;
        });

        RedemptionFulfilled::dispatch($fulfilled);

        return $fulfilled;
    }

    /**
     * Cancel a PENDING redemption. API_Design.md §7.6, P24.
     *
     * One database transaction: status becomes CANCELLED, the stock is returned,
     * and PointsService writes a REVERSAL row giving the points back, with the
     * admin's reason as its description. A FULFILLED redemption cannot be
     * cancelled: the student already has the item (System_Plan.md §6.1).
     * Nothing is dispatched; docs/ defines no notification for a cancel.
     */
    public function cancel(Redemption $redemption, string $reason): Redemption
    {
        return DB::transaction(function () use ($redemption, $reason) {
            $redemption = $this->lockPending($redemption);

            $redemption->update(['status' => Redemption::STATUS_CANCELLED]);

            Reward::whereKey($redemption->reward_id)->increment('stock_quantity', $redemption->quantity);

            $this->points->reverseRedemption($redemption, $reason);

            return $redemption;
        });
    }

    /**
     * Every redemption, newest first, for the admin list, with the student and
     * the reward eager-loaded.
     *
     * @return LengthAwarePaginator<int, Redemption>
     */
    public function list(): LengthAwarePaginator
    {
        return Redemption::with(['user', 'reward'])->latest('id')->paginate();
    }

    /**
     * Re-reads the redemption with a row lock as the first read of the
     * transaction, never trusting the route-bound copy, and refuses unless it is
     * still PENDING. This is what stops one redemption being both fulfilled and
     * cancelled (P24).
     */
    private function lockPending(Redemption $redemption): Redemption
    {
        $locked = Redemption::whereKey($redemption->id)->lockForUpdate()->firstOrFail();

        if (! $locked->isPending()) {
            throw RedemptionRefused::notPending($locked->status);
        }

        return $locked;
    }
}
