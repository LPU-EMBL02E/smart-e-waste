<?php

namespace Tests\Feature\Deposits;

use App\Modules\Deposits\Models\Transaction;
use App\Modules\Deposits\Services\DepositService;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The admin transaction pages. API_Design.md §7.4; System_Plan.md §5.6.
 */
class AdminTransactionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_it_excludes_voided_deposits_from_total_collected_weight(): void
    {
        // The factory's session weighs 184.6 g.
        Transaction::factory()->count(2)->create();
        Transaction::factory()->voided()->create();

        $this->assertSame(369.2, app(DepositService::class)->totalCollectedWeightGrams());
        $this->get(route('admin.transactions.index'))->assertOk()->assertSee('0.37 kg');
    }

    public function test_it_shows_the_session_values_on_the_transaction_page(): void
    {
        $transaction = Transaction::factory()->create();

        $this->get(route('admin.transactions.show', $transaction))
            ->assertOk()
            ->assertSee($transaction->transaction_code)
            ->assertSee('184.60 g')
            ->assertSee('cam_diff')
            ->assertSee('0.8700')
            ->assertSee('3200 ms')
            ->assertSee(route('admin.transactions.void', $transaction));
    }

    public function test_it_hides_the_void_form_once_voided(): void
    {
        $transaction = Transaction::factory()->voided()->create();

        $this->get(route('admin.transactions.show', $transaction))
            ->assertOk()
            ->assertSee('Test void')
            ->assertDontSee(route('admin.transactions.void', $transaction));
    }

    public function test_it_keeps_students_out_of_the_admin_transaction_pages(): void
    {
        $transaction = Transaction::factory()->create();
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.transactions.index'))->assertForbidden();
        $this->get(route('admin.transactions.show', $transaction))->assertForbidden();
        $this->post(route('admin.transactions.void', $transaction), ['void_reason' => 'x'])->assertForbidden();

        $this->assertTrue($transaction->fresh()->canBeVoided());
    }
}
