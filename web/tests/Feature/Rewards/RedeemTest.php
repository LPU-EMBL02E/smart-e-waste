<?php

namespace Tests\Feature\Rewards;

use App\Modules\Identity\Models\User;
use App\Modules\Rewards\Models\PointLedgerEntry;
use App\Modules\Rewards\Models\Redemption;
use App\Modules\Rewards\Models\Reward;
use App\Modules\Rewards\Services\PointsService;
use App\Modules\Rewards\Support\LedgerEntryType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Redeeming a reward. API_Design.md §7.1, §7.3, §7.6 ("Redeem a reward"), P25;
 * System_Plan.md §6.1.
 *
 * The lock order cannot be raced on SQLite; the MariaDB check in Track C step
 * C6 covers it.
 */
class RedeemTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private Reward $reward;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->create();
        $this->reward = Reward::factory()->create(['points_cost' => 50, 'stock_quantity' => 10]);
        $this->actingAs($this->student);
    }

    public function test_it_redeems_and_writes_a_redeem_row_for_the_total(): void
    {
        $this->giveBalance(200);

        $this->redeem(3)->assertRedirect(route('redemptions.index'))->assertSessionHas('status');

        $redemption = Redemption::sole();
        $this->assertMatchesRegularExpression('/^RDM-\d{8}-[A-Z0-9]{6}$/', $redemption->redemption_code);
        $this->assertSame($this->student->id, $redemption->user_id);
        $this->assertSame(3, $redemption->quantity);
        $this->assertSame(150, $redemption->points_cost);
        $this->assertSame(Redemption::STATUS_PENDING, $redemption->status);

        $entry = PointLedgerEntry::where('entry_type', LedgerEntryType::Redeem)->sole();
        $this->assertSame(-150, $entry->points_delta);
        $this->assertSame($redemption->id, $entry->redemption_id);
        $this->assertSame(50, app(PointsService::class)->balanceFor($this->student));
    }

    public function test_it_decrements_stock_by_the_quantity(): void
    {
        $this->giveBalance(200);

        $this->redeem(4);

        $this->assertSame(6, $this->reward->fresh()->stock_quantity);
    }

    public function test_it_refuses_when_the_balance_is_too_low_and_changes_nothing(): void
    {
        $this->giveBalance(149);

        $this->redeem(3)->assertRedirect()->assertSessionHasErrors('redemption');

        $this->assertNothingChanged(balance: 149);
    }

    public function test_it_refuses_when_stock_is_short_and_changes_nothing(): void
    {
        $this->giveBalance(1000);

        $this->redeem(11)->assertSessionHasErrors('redemption');

        $this->assertNothingChanged(balance: 1000);
    }

    public function test_it_refuses_a_user_whose_balance_is_negative(): void
    {
        // A void after the points were spent leaves the balance below zero.
        $this->giveBalance(-20);

        $this->redeem(1)->assertSessionHasErrors('redemption');

        $this->assertNothingChanged(balance: -20);
    }

    public function test_it_refuses_an_inactive_reward(): void
    {
        $this->giveBalance(200);
        $this->reward->update(['is_active' => false]);

        $this->redeem(1)->assertSessionHasErrors('reward_id');

        $this->assertNothingChanged(balance: 200);
    }

    public function test_it_charges_the_price_at_redemption_time(): void
    {
        $this->giveBalance(200);
        $this->redeem(1);

        $this->reward->update(['points_cost' => 80]);
        $this->redeem(1);

        $this->assertSame([50, 80], Redemption::orderBy('id')->pluck('points_cost')->all());
        $this->assertSame(70, app(PointsService::class)->balanceFor($this->student));
    }

    public function test_it_lists_only_the_users_own_redemptions(): void
    {
        Redemption::factory()->for($this->student)->for($this->reward)->create(['redemption_code' => 'RDM-20261030-MINE01']);
        Redemption::factory()->for($this->reward)->create(['redemption_code' => 'RDM-20261030-OTHER1']);

        $this->get(route('redemptions.index'))
            ->assertOk()
            ->assertSee('RDM-20261030-MINE01')
            ->assertDontSee('RDM-20261030-OTHER1');
    }

    private function redeem(int $quantity)
    {
        return $this->from(route('rewards.show', $this->reward))
            ->post(route('redemptions.store'), ['reward_id' => $this->reward->id, 'quantity' => $quantity]);
    }

    private function giveBalance(int $points): void
    {
        PointLedgerEntry::factory()->for($this->student)->create(['points_delta' => $points]);
    }

    private function assertNothingChanged(int $balance): void
    {
        $this->assertDatabaseCount('redemptions', 0);
        $this->assertSame(0, PointLedgerEntry::where('entry_type', LedgerEntryType::Redeem)->count());
        $this->assertSame(10, $this->reward->fresh()->stock_quantity);
        $this->assertSame($balance, app(PointsService::class)->balanceFor($this->student));
    }
}
