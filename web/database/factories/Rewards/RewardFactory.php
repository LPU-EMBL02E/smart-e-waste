<?php

namespace Database\Factories\Rewards;

use App\Modules\Rewards\Models\Reward;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Rewards for tests. Database_Schema.md §10.
 *
 * The cost and stock are throwaway test numbers. Real costs need instructor
 * approval (System_Plan.md §10) and are entered on the admin reward pages.
 *
 * The default reward is active and in stock.
 *
 * @extends Factory<Reward>
 */
class RewardFactory extends Factory
{
    protected $model = Reward::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'description' => null,
            'points_cost' => 50,
            'stock_quantity' => 10,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function outOfStock(): static
    {
        return $this->state(['stock_quantity' => 0]);
    }
}
