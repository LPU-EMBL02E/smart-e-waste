<?php

namespace Tests\Feature\Rewards;

use App\Modules\Deposits\Models\DepositSession;
use App\Modules\Deposits\Models\Transaction;
use App\Modules\Identity\Models\User;
use App\Modules\Rewards\Models\PointLedgerEntry;
use App\Modules\Rewards\Models\Redemption;
use App\Modules\Rewards\Models\RewardRule;
use App\Modules\Rewards\Services\PointsService;
use App\Modules\Rewards\Support\LedgerEntryType;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * PointsService, the one writer of point_ledger. System_Plan.md §5.7 rules 5
 * and 7, §6.1; API_Design.md §5.4 step 5, §7.3, §7.6; Database_Schema.md §9.
 */
class PointsServiceTest extends TestCase
{
    use RefreshDatabase;

    private PointsService $points;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->points = app(PointsService::class);
        $this->student = User::factory()->create();
    }

    public function test_it_sums_the_balance_from_the_ledger(): void
    {
        $this->credit($this->earn(18));
        $this->credit($this->earn(30));
        PointLedgerEntry::create([
            'user_id' => $this->student->id,
            'entry_type' => LedgerEntryType::Adjustment,
            'points_delta' => -50,
        ]);
        $this->credit(Transaction::factory()->create(['points_awarded' => 99]));

        $this->assertSame(-2, $this->points->balanceFor($this->student));
    }

    public function test_it_counts_today_from_manila_midnight(): void
    {
        // 23:30 on 29 October in Manila, then 00:10 on the 30th: both are on
        // 29 October in UTC, so only a Manila midnight separates them.
        $this->travelTo(Carbon::parse('2026-10-29 23:30', 'Asia/Manila'));
        $this->earn(18);
        $this->travelTo(Carbon::parse('2026-10-30 00:10', 'Asia/Manila'));
        $this->earn(30);

        $this->assertSame(30, $this->points->pointsEarnedToday($this->student));
    }

    public function test_it_counts_voided_transactions_toward_the_cap(): void
    {
        $this->earn(40, voided: true);
        $rule = RewardRule::factory()->dailyCap(40)->make();

        $this->assertSame(40, $this->points->pointsEarnedToday($this->student));
        $this->assertFalse($this->points->isUnderDailyCap($this->student, $rule));
    }

    public function test_it_treats_a_null_cap_as_no_cap(): void
    {
        $this->earn(100_000);

        $this->assertTrue($this->points->isUnderDailyCap($this->student, RewardRule::factory()->make()));
    }

    public function test_it_credits_a_deposit_once(): void
    {
        $transaction = $this->earn(18);
        $entry = $this->points->creditDeposit($transaction);

        $this->assertSame(LedgerEntryType::Earn, $entry->entry_type);
        $this->assertSame(18, $entry->points_delta);
        $this->assertSame($this->student->id, $entry->user_id);

        try {
            $this->points->creditDeposit($transaction);
            $this->fail('A second EARN row for the same transaction was written.');
        } catch (UniqueConstraintViolationException) {
            $this->assertSame(1, PointLedgerEntry::count());
        }
    }

    public function test_it_counts_earn_minus_transaction_reversals_as_points_earned(): void
    {
        $this->credit($this->earn(30));
        $voided = $this->earn(18);
        $this->credit($voided);
        $this->points->reverseTransaction($voided, 'Not e-waste');

        $this->assertSame(30, $this->points->pointsEarnedBy($this->student));
    }

    public function test_it_ignores_redemptions_in_points_earned(): void
    {
        $this->credit($this->earn(100));
        $redemption = Redemption::factory()->for($this->student)->create(['points_cost' => 60]);
        $this->points->debitRedemption($redemption);
        $this->points->reverseRedemption($redemption, 'Out of stock at the desk');
        $this->points->debitRedemption(Redemption::factory()->for($this->student)->create(['points_cost' => 40]));

        $this->assertSame(100, $this->points->pointsEarnedBy($this->student));
        $this->assertSame(60, $this->points->balanceFor($this->student));
    }

    public function test_it_debits_the_total_cost_of_a_redemption(): void
    {
        $redemption = Redemption::factory()->for($this->student)->create(['quantity' => 3, 'points_cost' => 150]);

        $entry = $this->points->debitRedemption($redemption);

        $this->assertSame(LedgerEntryType::Redeem, $entry->entry_type);
        $this->assertSame(-150, $entry->points_delta);
        $this->assertSame($redemption->id, $entry->redemption_id);
        $this->assertNull($entry->transaction_id);
        $this->assertSame($this->student->id, $entry->user_id);
    }

    public function test_it_reverses_a_transaction_with_the_opposite_delta(): void
    {
        $transaction = $this->earn(18);
        $this->credit($transaction);
        $reason = str_repeat('Heavy non-e-waste item. ', 20);

        $entry = $this->points->reverseTransaction($transaction, $reason);

        $this->assertSame(LedgerEntryType::Reversal, $entry->entry_type);
        $this->assertSame(-18, $entry->points_delta);
        $this->assertSame($transaction->id, $entry->transaction_id);
        $this->assertSame($this->student->id, $entry->user_id);
        // A 500-character void reason is cut to fit the 255-character column.
        $this->assertSame(255, mb_strlen($entry->description));
        $this->assertStringStartsWith('Heavy non-e-waste item.', $entry->description);
        $this->assertSame(0, $this->points->balanceFor($this->student));
    }

    public function test_it_refuses_a_second_reversal_of_the_same_transaction(): void
    {
        $transaction = $this->earn(18);
        $this->points->reverseTransaction($transaction, 'First');

        $this->expectException(UniqueConstraintViolationException::class);

        $this->points->reverseTransaction($transaction, 'Second');
    }

    public function test_it_gives_the_points_back_when_a_redemption_is_reversed(): void
    {
        $this->credit($this->earn(100));
        $redemption = Redemption::factory()->for($this->student)->create(['points_cost' => 60]);
        $this->points->debitRedemption($redemption);

        $entry = $this->points->reverseRedemption($redemption, 'Student changed their mind');

        $this->assertSame(LedgerEntryType::Reversal, $entry->entry_type);
        $this->assertSame(60, $entry->points_delta);
        $this->assertSame($redemption->id, $entry->redemption_id);
        $this->assertSame('Student changed their mind', $entry->description);
        $this->assertSame(100, $this->points->balanceFor($this->student));
    }

    private function earn(int $points, bool $voided = false): Transaction
    {
        $factory = Transaction::factory()
            ->for(DepositSession::factory()->completed()->for($this->student), 'session');

        return ($voided ? $factory->voided() : $factory)->create(['points_awarded' => $points]);
    }

    private function credit(Transaction $transaction): void
    {
        $this->points->creditDeposit($transaction);
    }
}
