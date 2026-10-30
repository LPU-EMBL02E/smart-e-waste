<?php

namespace App\Modules\Rewards\Services;

use App\Modules\Rewards\Models\RewardRule;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Versioning the points rule. API_Design.md §7.6 ("Save a points rule"),
 * System_Plan.md §5.7 rule 6.
 *
 * A rule is never edited. Saving closes the current one and inserts a new one,
 * so past transactions keep the rule they were priced with.
 *
 * The only writer of `reward_rules` (System_Plan.md §4).
 */
class RewardRuleService
{
    /**
     * Save a new rule, in one database transaction:
     *
     *   1. Set effective_to = now on the rule currently in effect, if any.
     *   2. Insert the new rule with effective_from = now.
     *
     * The new rule takes effect at once; future-dated rules are not supported
     * (API_Design.md P38). This ordering inside one transaction is what keeps
     * rules from ever overlapping, which the database cannot enforce.
     *
     * The validation rules — points_per_gram > 0, minimum_weight_g at least
     * 1 ÷ points_per_gram so an accepted deposit never earns zero points,
     * maximum_weight_g above the minimum and no more than
     * config('ewaste.hardware_max_weight_g') — belong in the FormRequest.
     */
    public function save(array $attributes): RewardRule
    {
        return DB::transaction(function () use ($attributes) {
            // One timestamp for both ends, so there is neither a gap nor an
            // overlap. The lock is the first read: a second admin saving at the
            // same moment waits, then closes the rule this one inserted.
            $now = now();

            RewardRule::inEffectAt($now)->lockForUpdate()->first()
                ?->update(['effective_to' => $now]);

            return RewardRule::create([
                ...$attributes,
                'effective_from' => $now,
                'effective_to' => null,
            ]);
        });
    }

    /**
     * Every rule, newest first, for the points-rate page.
     *
     * @return LengthAwarePaginator<int, RewardRule>
     */
    public function history(): LengthAwarePaginator
    {
        return RewardRule::latest('effective_from')->latest('id')->paginate();
    }

    /**
     * The rule in effect now, or null. GET /device/config still answers 200 when
     * this is null so the bin can boot; deposits are refused later with
     * NO_REWARD_RULE (API_Design.md §5.2).
     */
    public function current(): ?RewardRule
    {
        return RewardRule::inEffectAt(now())->first();
    }
}
