<?php

namespace Tests\Feature\Rewards;

use App\Modules\Deposits\Models\DepositSession;
use App\Modules\Deposits\Models\Transaction;
use App\Modules\Identity\Models\User;
use App\Modules\Rewards\Models\PointLedgerEntry;
use App\Modules\Rewards\Models\RewardRule;
use App\Modules\Rewards\Services\PointsService;
use App\Modules\Rewards\Support\LedgerEntryType;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The ledger methods of the deposit path. System_Plan.md §5.7 rules 5 and 7,
 * API_Design.md §5.4 step 5, Database_Schema.md §9.
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
