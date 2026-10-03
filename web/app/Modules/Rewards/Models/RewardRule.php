<?php

namespace App\Modules\Rewards\Models;

use App\Modules\Deposits\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A versioned points rule. Database_Schema.md §7, System_Plan.md §5.7.
 *
 * Four variables, all set by an admin on /admin/points-rate:
 *   points_per_gram    grams -> points
 *   minimum_weight_g   smallest accepted deposit
 *   maximum_weight_g   largest accepted deposit (NULL = no limit)
 *   daily_points_cap   most points one student can earn in a day (NULL = no cap)
 *
 * Rules are never edited. Changing a variable closes the current rule
 * (effective_to = now) and inserts a new one, so past transactions keep the rule
 * they were priced with. Rules never overlap; the application guarantees that,
 * the database cannot.
 *
 * A session uses the rule in effect when it OPENED, even if `complete` arrives
 * much later (System_Plan.md §5.7 rule 6).
 *
 * TODO(team): no values are set in the plan. points_per_gram,
 * minimum_weight_g, maximum_weight_g and daily_points_cap all need instructor
 * approval before the first rule is created (guidelines, Step 9).
 */
class RewardRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'points_per_gram',
        'minimum_weight_g',
        'maximum_weight_g',
        'daily_points_cap',
        'effective_from',
        'effective_to',
    ];

    protected function casts(): array
    {
        return [
            'points_per_gram' => 'decimal:4',
            'minimum_weight_g' => 'decimal:2',
            'maximum_weight_g' => 'decimal:2',
            'daily_points_cap' => 'integer',
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    // ------------------------------------------------------------------ scopes

    /**
     * The rule in effect at a moment in time: effective_from at or before it,
     * and effective_to NULL or later. Exactly one row matches, because rules
     * never overlap (API_Design.md §4.1).
     */
    public function scopeInEffectAt(Builder $query, \DateTimeInterface $at): Builder
    {
        return $query->where('effective_from', '<=', $at)
            ->where(function (Builder $q) use ($at) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>', $at);
            });
    }

    // ----------------------------------------------------------------- helpers

    /**
     * Points for a weight: floor(net_weight_g × points_per_gram).
     *
     * Always rounds down, so the system never awards more than was weighed
     * (System_Plan.md §5.7 rule 2). The device never computes this.
     */
    public function pointsFor(float $netWeightGrams): int
    {
        return (int) floor($netWeightGrams * (float) $this->points_per_gram);
    }

    /**
     * Whether a weight is inside this rule's limits. Both bounds are inclusive
     * (API_Design.md P23). A NULL maximum_weight_g means no upper limit.
     */
    public function acceptsWeight(float $netWeightGrams): bool
    {
        if ($netWeightGrams < (float) $this->minimum_weight_g) {
            return false;
        }

        return $this->maximum_weight_g === null
            || $netWeightGrams <= (float) $this->maximum_weight_g;
    }

    public function hasDailyCap(): bool
    {
        return $this->daily_points_cap !== null;
    }
}
