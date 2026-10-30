<?php

namespace Tests\Feature\Rewards;

use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Models\Notification;
use App\Modules\Notifications\Support\NotificationType;
use App\Modules\Rewards\Events\RedemptionFulfilled;
use App\Modules\Rewards\Models\PointLedgerEntry;
use App\Modules\Rewards\Models\Redemption;
use App\Modules\Rewards\Models\Reward;
use App\Modules\Rewards\Services\PointsService;
use App\Modules\Rewards\Support\LedgerEntryType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Fulfilling and cancelling a redemption. API_Design.md §7.4, §7.6 ("Fulfil or
 * cancel a redemption"), §7.7, P24; System_Plan.md §6.1.
 */
class AdminRedemptionTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private Reward $reward;

    private Redemption $redemption;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->create();
        $this->reward = Reward::factory()->create(['stock_quantity' => 7]);
        $this->redemption = Redemption::factory()->for($this->student)->for($this->reward)
            ->create(['quantity' => 3, 'points_cost' => 150]);

        // The balance after the redemption: 200 earned, 150 spent.
        PointLedgerEntry::factory()->for($this->student)->create(['points_delta' => 200]);
        PointLedgerEntry::factory()->for($this->student)->create([
            'entry_type' => LedgerEntryType::Redeem,
            'points_delta' => -150,
            'redemption_id' => $this->redemption->id,
        ]);

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_it_fulfils_and_notifies_the_student(): void
    {
        $this->post(route('admin.redemptions.fulfil', $this->redemption))
            ->assertRedirect()
            ->assertSessionHas('status');

        $redemption = $this->redemption->fresh();
        $this->assertSame(Redemption::STATUS_FULFILLED, $redemption->status);
        $this->assertNotNull($redemption->fulfilled_at);

        $notification = Notification::sole();
        $this->assertSame($this->student->id, $notification->user_id);
        $this->assertSame(NotificationType::RedemptionFulfilled, $notification->type);
        $this->assertStringContainsString($this->redemption->redemption_code, $notification->message);
    }

    public function test_it_cancels_and_returns_the_points_and_stock(): void
    {
        $this->post(route('admin.redemptions.cancel', $this->redemption), ['cancel_reason' => 'Item damaged'])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame(Redemption::STATUS_CANCELLED, $this->redemption->fresh()->status);
        $this->assertSame(10, $this->reward->fresh()->stock_quantity);

        $reversal = PointLedgerEntry::where('entry_type', LedgerEntryType::Reversal)->sole();
        $this->assertSame(150, $reversal->points_delta);
        $this->assertSame($this->redemption->id, $reversal->redemption_id);
        $this->assertSame('Item damaged', $reversal->description);
        $this->assertSame(200, app(PointsService::class)->balanceFor($this->student));
    }

    public function test_it_requires_a_cancel_reason(): void
    {
        $this->post(route('admin.redemptions.cancel', $this->redemption), ['cancel_reason' => ''])
            ->assertSessionHasErrors('cancel_reason');

        $this->assertTrue($this->redemption->fresh()->isPending());
        $this->assertSame(0, PointLedgerEntry::where('entry_type', LedgerEntryType::Reversal)->count());
    }

    public function test_it_refuses_to_cancel_a_fulfilled_redemption(): void
    {
        $this->redemption->update(['status' => Redemption::STATUS_FULFILLED]);

        $this->post(route('admin.redemptions.cancel', $this->redemption), ['cancel_reason' => 'Too late'])
            ->assertSessionHasErrors('redemption');

        $this->assertSame(Redemption::STATUS_FULFILLED, $this->redemption->fresh()->status);
        $this->assertSame(7, $this->reward->fresh()->stock_quantity);
        $this->assertSame(0, PointLedgerEntry::where('entry_type', LedgerEntryType::Reversal)->count());
    }

    public function test_it_refuses_to_fulfil_a_cancelled_redemption(): void
    {
        Event::fake([RedemptionFulfilled::class]);
        $this->redemption->update(['status' => Redemption::STATUS_CANCELLED]);

        $this->post(route('admin.redemptions.fulfil', $this->redemption))
            ->assertSessionHasErrors('redemption');

        $redemption = $this->redemption->fresh();
        $this->assertSame(Redemption::STATUS_CANCELLED, $redemption->status);
        $this->assertNull($redemption->fulfilled_at);
        Event::assertNotDispatched(RedemptionFulfilled::class);
    }

    public function test_it_dispatches_nothing_on_cancel(): void
    {
        $dispatched = [];
        Event::listen('App\\Modules\\*', function (string $event) use (&$dispatched) {
            $dispatched[] = $event;
        });

        $this->post(route('admin.redemptions.cancel', $this->redemption), ['cancel_reason' => 'Student asked']);

        $this->assertSame([], $dispatched);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_it_lists_redemptions_with_actions_only_while_pending(): void
    {
        Redemption::factory()->for($this->reward)->fulfilled()->create(['redemption_code' => 'RDM-20261030-DONE01']);

        $this->get(route('admin.redemptions.index'))
            ->assertOk()
            ->assertSee($this->redemption->redemption_code)
            ->assertSee('RDM-20261030-DONE01')
            ->assertSee(route('admin.redemptions.fulfil', $this->redemption))
            ->assertDontSee(route('admin.redemptions.fulfil', Redemption::where('redemption_code', 'RDM-20261030-DONE01')->sole()));
    }

    public function test_it_keeps_students_out(): void
    {
        $this->actingAs($this->student);

        $this->get(route('admin.redemptions.index'))->assertForbidden();
        $this->post(route('admin.redemptions.fulfil', $this->redemption))->assertForbidden();
        $this->post(route('admin.redemptions.cancel', $this->redemption), ['cancel_reason' => 'x'])->assertForbidden();

        $this->assertTrue($this->redemption->fresh()->isPending());
    }
}
