<?php

namespace Tests\Feature\Deposits;

use App\Modules\Deposits\Models\DepositSession;
use App\Modules\Deposits\Models\Transaction;
use App\Modules\Identity\Models\User;
use Database\Factories\Deposits\TransactionFactory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A user's own deposit history at /transactions. API_Design.md §7.3 and §7.1.
 */
class TransactionHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->create();
        $this->actingAs($this->student);
    }

    public function test_it_lists_only_the_users_own_transactions(): void
    {
        $own = $this->transactionFor($this->student)->create();
        $voided = $this->transactionFor($this->student)->voided()->create();
        $someoneElses = $this->transactionFor(User::factory()->create())->create();

        $this->get(route('transactions.index'))
            ->assertOk()
            ->assertSee($own->transaction_code)
            ->assertSee($voided->transaction_code)
            ->assertSee('Voided')
            ->assertDontSee($someoneElses->transaction_code);
    }

    public function test_it_paginates_the_history(): void
    {
        $this->transactionFor($this->student)->count(16)->create();

        $this->get(route('transactions.index'))
            ->assertOk()
            ->assertViewHas('transactions', fn (LengthAwarePaginator $page) => $page->total() === 16 && $page->count() === 15);

        $this->get(route('transactions.index', ['page' => 2]))
            ->assertOk()
            ->assertViewHas('transactions', fn (LengthAwarePaginator $page) => $page->count() === 1);
    }

    /** One completed session of $user per transaction: session_id is unique. */
    private function transactionFor(User $user): TransactionFactory
    {
        return Transaction::factory()->state(fn () => [
            'session_id' => DepositSession::factory()->completed()->for($user),
        ]);
    }
}
