<?php

namespace Database\Factories\Deposits;

use App\Modules\Deposits\Models\DepositSession;
use App\Modules\Deposits\Models\Transaction;
use App\Modules\Rewards\Models\RewardRule;
use App\Support\ReferenceCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Transactions for tests. Database_Schema.md §8.
 *
 * Builds the row only. The EARN ledger row that the application always writes
 * with it is not created here: tests that need a balance write it through
 * PointsService, the one writer of point_ledger.
 *
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        return [
            'transaction_code' => ReferenceCode::transaction(),
            'session_id' => DepositSession::factory()->completed(),
            'reward_rule_id' => RewardRule::factory(),
            'points_awarded' => 18,
            'status' => Transaction::STATUS_COMPLETED,
        ];
    }

    public function voided(): static
    {
        return $this->state([
            'status' => Transaction::STATUS_VOIDED,
            'void_reason' => 'Test void',
            'voided_at' => now(),
        ]);
    }
}
