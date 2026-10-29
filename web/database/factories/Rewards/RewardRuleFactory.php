<?php

namespace Database\Factories\Rewards;

use App\Modules\Rewards\Models\RewardRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Points rules for tests. Database_Schema.md §7.
 *
 * The numbers are throwaway test values chosen to keep the arithmetic easy
 * (0.1 points per gram, so 184.6 g earns 18). They are NOT the project's point
 * values: those need instructor approval and are entered on the points-rate
 * page (System_Plan.md §5.7).
 *
 * The default rule is in effect now and has no daily cap.
 *
 * @extends Factory<RewardRule>
 */
class RewardRuleFactory extends Factory
{
    protected $model = RewardRule::class;

    public function definition(): array
    {
        return [
            'name' => 'Test rule',
            'points_per_gram' => 0.1,
            'minimum_weight_g' => 10,
            'maximum_weight_g' => 2000,
            'daily_points_cap' => null,
            'effective_from' => now()->subDay(),
            'effective_to' => null,
        ];
    }

    public function dailyCap(int $points): static
    {
        return $this->state(['daily_points_cap' => $points]);
    }

    /** A past rule: in effect from $from until $to. */
    public function between(\DateTimeInterface $from, \DateTimeInterface $to): static
    {
        return $this->state(['effective_from' => $from, 'effective_to' => $to]);
    }
}
