<?php

namespace Tests\Feature\Rewards;

use App\Modules\Identity\Models\User;
use App\Modules\Rewards\Models\RewardRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Saving a points rule. API_Design.md §7.6 ("Save a points rule"), P38;
 * System_Plan.md §5.7 rule 6.
 */
class RewardRuleTest extends TestCase
{
    use RefreshDatabase;

    private const FORM = [
        'name' => 'Second rule',
        'points_per_gram' => 0.2,
        'minimum_weight_g' => 5,
        'maximum_weight_g' => 1500,
        'daily_points_cap' => 300,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_it_closes_the_current_rule_when_a_new_one_is_saved(): void
    {
        $old = RewardRule::factory()->create();
        $this->freezeSecond();

        $this->post(route('admin.points-rate.store'), self::FORM)
            ->assertRedirect(route('admin.points-rate.show'));

        $new = RewardRule::where('name', 'Second rule')->sole();
        $this->assertTrue($old->fresh()->effective_to->equalTo(now()));
        $this->assertTrue($new->effective_from->equalTo(now()));
        $this->assertNull($new->effective_to);
        $this->assertTrue(RewardRule::inEffectAt(now())->sole()->is($new));
    }

    public function test_it_never_leaves_two_rules_in_effect(): void
    {
        $this->post(route('admin.points-rate.store'), self::FORM);
        $this->post(route('admin.points-rate.store'), ['name' => 'Third rule'] + self::FORM);
        $this->post(route('admin.points-rate.store'), ['name' => 'Fourth rule'] + self::FORM);

        $this->assertSame(3, RewardRule::count());
        $this->assertSame(1, RewardRule::whereNull('effective_to')->count());
        $this->assertSame('Fourth rule', RewardRule::inEffectAt(now())->sole()->name);
    }

    public function test_it_refuses_a_minimum_below_one_point(): void
    {
        // At 0.2 points per gram, anything under 5 g would earn zero points.
        $this->post(route('admin.points-rate.store'), ['minimum_weight_g' => 4.99] + self::FORM)
            ->assertSessionHasErrors('minimum_weight_g');

        $this->assertDatabaseCount('reward_rules', 0);
    }

    public function test_it_refuses_a_maximum_not_above_the_minimum(): void
    {
        $this->post(route('admin.points-rate.store'), ['maximum_weight_g' => 5] + self::FORM)
            ->assertSessionHasErrors('maximum_weight_g');

        $this->assertDatabaseCount('reward_rules', 0);
    }
}
