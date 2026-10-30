<?php

namespace Database\Factories\Rewards;

use App\Modules\Deposits\Models\DepositSession;
use App\Modules\Deposits\Models\Transaction;
use App\Modules\Identity\Models\User;
use App\Modules\Rewards\Models\PointLedgerEntry;
use App\Modules\Rewards\Support\LedgerEntryType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Ledger rows for tests: the quick way to give a test user a balance.
 * Database_Schema.md §9.
 *
 * Test setup only. In the application, PointsService is the one writer of
 * point_ledger (System_Plan.md §4).
 *
 * The default row is a positive ADJUSTMENT with no link. earn() is an EARN row
 * with a real transaction behind it, from a completed session of the same user,
 * which is what points earned and the leaderboards count.
 *
 * @extends Factory<PointLedgerEntry>
 */
class PointLedgerEntryFactory extends Factory
{
    protected $model = PointLedgerEntry::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'entry_type' => LedgerEntryType::Adjustment,
            'points_delta' => 100,
            'description' => 'Test balance',
        ];
    }

    public function earn(int $points = 100): static
    {
        return $this->state(function (array $attributes) use ($points) {
            $userId = $attributes['user_id'] instanceof Factory
                ? $attributes['user_id']->create()->getKey()
                : $attributes['user_id'];

            return [
                'user_id' => $userId,
                'entry_type' => LedgerEntryType::Earn,
                'points_delta' => $points,
                'description' => null,
                'transaction_id' => Transaction::factory()
                    ->for(DepositSession::factory()->completed()->state(['user_id' => $userId]), 'session')
                    ->state(['points_awarded' => $points]),
            ];
        });
    }
}
