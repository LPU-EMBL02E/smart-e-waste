<?php

namespace Tests\Feature\Rewards;

use App\Modules\Identity\Models\User;
use App\Modules\Rewards\Models\PointLedgerEntry;
use App\Modules\Rewards\Models\Reward;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The reward catalogue. API_Design.md §7.3, §7.4, §7.6 ("Create or edit a
 * reward"), P33, P41.
 */
class RewardCatalogueTest extends TestCase
{
    use RefreshDatabase;

    private const FORM = [
        'name' => 'Eco bag',
        'description' => 'Reusable tote',
        'points_cost' => 120,
        'stock_quantity' => 25,
        'is_active' => '1',
    ];

    public function test_it_creates_and_edits_a_reward(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->post(route('admin.rewards.store'), self::FORM)
            ->assertRedirect(route('admin.rewards.index'));

        $reward = Reward::sole();
        $this->assertSame('Eco bag', $reward->name);
        $this->assertSame(120, $reward->points_cost);
        $this->assertSame(25, $reward->stock_quantity);
        $this->assertTrue($reward->is_active);

        $this->put(route('admin.rewards.update', $reward), ['points_cost' => 150, 'is_active' => '0'] + self::FORM)
            ->assertRedirect(route('admin.rewards.index'));

        $reward->refresh();
        $this->assertSame(150, $reward->points_cost);
        $this->assertFalse($reward->is_active);
    }

    public function test_it_refuses_a_cost_below_one_point(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->post(route('admin.rewards.store'), ['points_cost' => 0] + self::FORM)
            ->assertSessionHasErrors('points_cost');

        $this->assertDatabaseCount('rewards', 0);
    }

    public function test_it_shows_students_only_active_rewards(): void
    {
        $active = Reward::factory()->create(['name' => 'Eco bag']);
        $inactive = Reward::factory()->inactive()->create(['name' => 'Retired mug']);
        $student = User::factory()->create();
        PointLedgerEntry::factory()->for($student)->create(['points_delta' => 100]);
        $this->actingAs($student);

        $this->get(route('rewards.index'))->assertOk()->assertSee('Eco bag')->assertDontSee('Retired mug');
        $this->get(route('rewards.show', $active))->assertOk()->assertSee('Redeem');
        $this->get(route('rewards.show', $inactive))->assertNotFound();
    }

    public function test_it_keeps_students_out_of_the_admin_reward_pages(): void
    {
        $reward = Reward::factory()->create();
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.rewards.index'))->assertForbidden();
        $this->post(route('admin.rewards.store'), self::FORM)->assertForbidden();
        $this->put(route('admin.rewards.update', $reward), self::FORM)->assertForbidden();
        $this->assertDatabaseCount('rewards', 1);
    }
}
