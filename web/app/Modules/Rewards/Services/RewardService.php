<?php

namespace App\Modules\Rewards\Services;

use App\Modules\Rewards\Models\Reward;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * The reward catalogue. API_Design.md §7.3, §7.4 and §7.6 ("Create or edit a
 * reward"), Database_Schema.md §10.
 *
 * Writes `rewards` for the admin pages. An admin sets the stock here;
 * redeeming and cancelling move it only through RedemptionService's guarded
 * updates (System_Plan.md §6.1). A price change never touches past
 * redemptions, which store their own cost.
 *
 * No delete: a reward is switched off with is_active (P33).
 */
class RewardService
{
    public function create(array $attributes): Reward
    {
        return Reward::create($attributes);
    }

    public function update(Reward $reward, array $attributes): Reward
    {
        $reward->update($attributes);

        return $reward;
    }

    /**
     * Every reward, active or not, for the admin list.
     *
     * @return LengthAwarePaginator<int, Reward>
     */
    public function list(): LengthAwarePaginator
    {
        return Reward::orderBy('name')->paginate();
    }

    /**
     * The student catalogue: active rewards only (API_Design.md §7.3).
     *
     * @return LengthAwarePaginator<int, Reward>
     */
    public function catalogue(): LengthAwarePaginator
    {
        return Reward::active()->orderBy('name')->paginate();
    }
}
