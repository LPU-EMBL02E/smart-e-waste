<?php

namespace Database\Factories\Rewards;

use App\Modules\Identity\Models\User;
use App\Modules\Rewards\Models\Redemption;
use App\Modules\Rewards\Models\Reward;
use App\Support\ReferenceCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Redemptions for tests, one state per status. Database_Schema.md §11.
 *
 * Builds the row only. The REDEEM ledger row the application always writes with
 * it is not created here; tests that need one write it through PointsService,
 * the one writer of point_ledger.
 *
 * @extends Factory<Redemption>
 */
class RedemptionFactory extends Factory
{
    protected $model = Redemption::class;

    public function definition(): array
    {
        return [
            'redemption_code' => ReferenceCode::redemption(),
            'user_id' => User::factory(),
            'reward_id' => Reward::factory(),
            'quantity' => 1,
            'points_cost' => 50,
            'status' => Redemption::STATUS_PENDING,
        ];
    }

    public function fulfilled(): static
    {
        return $this->state([
            'status' => Redemption::STATUS_FULFILLED,
            'fulfilled_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => Redemption::STATUS_CANCELLED]);
    }
}
