<?php

namespace Tests\Feature\Deposits;

use App\Modules\Deposits\Models\Transaction;
use App\Modules\Identity\Models\User;
use App\Modules\Rewards\Models\PointLedgerEntry;
use App\Modules\Rewards\Services\PointsService;
use App\Modules\Rewards\Support\LedgerEntryType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Voiding a transaction. API_Design.md §7.6 ("Void a transaction"), P24, P41;
 * System_Plan.md §5.7 rule 5.
 */
class VoidTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $student;

    private Transaction $transaction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->create();
        $this->transaction = PointLedgerEntry::factory()->for($this->student)->earn(18)->create()->transaction;
        $this->admin = User::factory()->admin()->create();
        $this->actingAs($this->admin);
    }

    public function test_it_voids_a_completed_transaction_and_reverses_its_points(): void
    {
        $this->void('Not e-waste: a brick')->assertRedirect()->assertSessionHas('status');

        $transaction = $this->transaction->fresh();
        $this->assertSame(Transaction::STATUS_VOIDED, $transaction->status);
        $this->assertSame('Not e-waste: a brick', $transaction->void_reason);
        $this->assertSame($this->admin->id, $transaction->voided_by);
        $this->assertNotNull($transaction->voided_at);

        $reversal = PointLedgerEntry::where('entry_type', LedgerEntryType::Reversal)->sole();
        $this->assertSame(-18, $reversal->points_delta);
        $this->assertSame($this->transaction->id, $reversal->transaction_id);
        $this->assertSame($this->student->id, $reversal->user_id);

        $points = app(PointsService::class);
        $this->assertSame(0, $points->balanceFor($this->student));
        $this->assertSame(0, $points->pointsEarnedBy($this->student));
    }

    public function test_it_allows_the_balance_to_go_below_zero(): void
    {
        // The student already spent 10 of the 18 points.
        PointLedgerEntry::factory()->for($this->student)->create(['points_delta' => -10]);

        $this->void('Not e-waste');

        $this->assertSame(-10, app(PointsService::class)->balanceFor($this->student));
    }

    public function test_it_refuses_to_void_a_transaction_twice(): void
    {
        $this->void('First');

        $this->void('Second')->assertSessionHasErrors('transaction');

        $this->assertSame('First', $this->transaction->fresh()->void_reason);
        $this->assertSame(1, PointLedgerEntry::where('entry_type', LedgerEntryType::Reversal)->count());
    }

    public function test_it_requires_a_void_reason(): void
    {
        $this->void('')->assertSessionHasErrors('void_reason');

        $this->assertTrue($this->transaction->fresh()->canBeVoided());
        $this->assertSame(0, PointLedgerEntry::where('entry_type', LedgerEntryType::Reversal)->count());
    }

    public function test_it_still_counts_a_voided_deposit_toward_the_daily_cap(): void
    {
        $this->void('Not e-waste');

        $this->assertSame(18, app(PointsService::class)->pointsEarnedToday($this->student));
    }

    private function void(string $reason)
    {
        return $this->from(route('admin.transactions.show', $this->transaction))
            ->post(route('admin.transactions.void', $this->transaction), ['void_reason' => $reason]);
    }
}
