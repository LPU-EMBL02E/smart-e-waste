<?php

namespace Tests\Feature\Rewards;

use App\Modules\Deposits\Models\DepositSession;
use App\Modules\Deposits\Models\Transaction;
use App\Modules\Deposits\Services\DepositService;
use App\Modules\Identity\Models\User;
use App\Modules\Rewards\Models\PointLedgerEntry;
use App\Modules\Rewards\Support\LedgerEntryType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The student's home page: balance and recent deposits. API_Design.md §7.3;
 * System_Plan.md §5.7 rule 7 and §6.1.
 */
class HomeTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->create();
        $this->actingAs($this->student);
    }

    public function test_it_shows_the_balance_from_the_ledger(): void
    {
        PointLedgerEntry::factory()->for($this->student)->earn(120)->create();
        PointLedgerEntry::factory()->for($this->student)->create(['points_delta' => 30]);
        PointLedgerEntry::factory()->for($this->student)->create([
            'entry_type' => LedgerEntryType::Redeem,
            'points_delta' => -50,
        ]);

        $this->get(route('home'))->assertOk()->assertSee('Points balance: 100');
    }

    public function test_it_shows_a_negative_balance_after_a_void(): void
    {
        $earn = PointLedgerEntry::factory()->for($this->student)->earn(100)->create();
        PointLedgerEntry::factory()->for($this->student)->create([
            'entry_type' => LedgerEntryType::Redeem,
            'points_delta' => -80,
        ]);

        app(DepositService::class)->void($earn->transaction, User::factory()->admin()->create(), 'Not e-waste');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Points balance: -80')
            ->assertSee('Voided');
    }

    public function test_it_lists_only_the_most_recent_transactions(): void
    {
        $transactions = Transaction::factory()
            ->count(config('ewaste.home_recent_transactions') + 1)
            ->sequence(fn ($sequence) => [
                'transaction_code' => "TXN-20261030-HOME0{$sequence->index}",
                'session_id' => DepositSession::factory()->completed()->for($this->student),
            ])
            ->create();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee($transactions->last()->transaction_code)
            ->assertDontSee($transactions->first()->transaction_code)
            ->assertSee(route('transactions.index'));
    }
}
