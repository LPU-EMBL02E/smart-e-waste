<?php

namespace Tests\Feature\Reporting;

use App\Modules\Deposits\Services\DepositService;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Modules\Reporting\Services\ReportingQueries;
use App\Modules\Rewards\Models\PointLedgerEntry;
use App\Modules\Rewards\Support\LedgerEntryType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The user and organization leaderboards. API_Design.md §7.3, §7.4, P31;
 * System_Plan.md §5.6; Database_Schema.md §9.
 *
 * Both rank by points earned: EARN rows minus the REVERSAL rows that link to a
 * transaction. Zero scores are not listed and ties are ordered by id
 * (web/docs/TRACK-D-REPORTING-AND-HISTORY.md, question 2).
 */
class LeaderboardTest extends TestCase
{
    use RefreshDatabase;

    private ReportingQueries $reporting;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reporting = app(ReportingQueries::class);
    }

    public function test_it_ranks_users_by_points_earned_not_balance(): void
    {
        $bigBalance = $this->userWhoEarned(50);
        PointLedgerEntry::factory()->for($bigBalance)->create(['points_delta' => 500]);
        $bigEarner = $this->userWhoEarned(100);

        $this->actingAs($bigEarner)
            ->get(route('leaderboard'))
            ->assertOk()
            ->assertSeeInOrder([$bigEarner->fullName(), '100', $bigBalance->fullName(), '50']);
    }

    public function test_it_does_not_lower_a_rank_when_a_user_redeems(): void
    {
        $redeemer = $this->userWhoEarned(100);
        $other = $this->userWhoEarned(80);
        PointLedgerEntry::factory()->for($redeemer)->create([
            'entry_type' => LedgerEntryType::Redeem,
            'points_delta' => -90,
        ]);

        $this->assertLeaderboard([$redeemer->id => 100, $other->id => 80]);
    }

    public function test_it_drops_a_voided_deposit_from_the_ranking(): void
    {
        $voided = PointLedgerEntry::factory()->earn(100)->create();
        $kept = $this->userWhoEarned(40);

        app(DepositService::class)->void($voided->transaction, User::factory()->admin()->create(), 'Not e-waste');

        $this->assertLeaderboard([$kept->id => 40]);
    }

    public function test_it_orders_equal_scores_by_id(): void
    {
        $first = $this->userWhoEarned(60);
        $second = $this->userWhoEarned(60);
        $top = $this->userWhoEarned(70);

        $this->assertLeaderboard([$top->id => 70, $first->id => 60, $second->id => 60]);
    }

    public function test_it_includes_admins_on_the_user_leaderboard(): void
    {
        $admin = User::factory()->admin()->create();
        PointLedgerEntry::factory()->for($admin)->earn(30)->create();

        $this->actingAs($admin)
            ->get(route('leaderboard'))
            ->assertOk()
            ->assertSee($admin->fullName());
    }

    public function test_it_counts_only_students_on_the_organization_leaderboard(): void
    {
        $organization = Organization::factory()->create();
        $this->userWhoEarned(40, ['organization_id' => $organization->id]);

        // Admins have no organization. One that has one anyway still doesn't count.
        PointLedgerEntry::factory()->for(User::factory()->admin())->earn(500)->create();
        PointLedgerEntry::factory()->for(User::factory()->admin()->for($organization))->earn(500)->create();

        $this->assertSame(
            [$organization->id => 40],
            $this->reporting->organizationLeaderboard()->pluck('points_earned', 'id')->all(),
        );

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.leaderboard.organizations'))
            ->assertOk()
            ->assertSeeInOrder([$organization->name, '40']);
    }

    public function test_it_scores_an_organization_by_its_current_students(): void
    {
        [$left, $joined] = Organization::factory()->count(2)->create();
        $mover = $this->userWhoEarned(70, ['organization_id' => $left->id]);
        $this->userWhoEarned(20, ['organization_id' => $joined->id]);

        $mover->update(['organization_id' => $joined->id]);

        $this->assertSame(
            [$joined->id => 90],
            $this->reporting->organizationLeaderboard()->pluck('points_earned', 'id')->all(),
        );
    }

    public function test_it_runs_a_fixed_number_of_queries_however_many_users(): void
    {
        $this->seedEarners(3);
        $fewUsers = $this->countQueries();

        $this->seedEarners(27);
        $manyUsers = $this->countQueries();

        $this->assertSame(2, $fewUsers);
        $this->assertSame($fewUsers, $manyUsers);
    }

    public function test_it_keeps_students_out_of_the_organization_leaderboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.leaderboard.organizations'))
            ->assertForbidden();
    }

    // ---------------------------------------------------------------- helpers

    private function userWhoEarned(int $points, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        PointLedgerEntry::factory()->for($user)->earn($points)->create();

        return $user;
    }

    /** @param  array<int, int>  $expected  user id => points earned, in rank order */
    private function assertLeaderboard(array $expected): void
    {
        $this->assertSame($expected, $this->reporting->userLeaderboard()->pluck('points_earned', 'id')->all());
    }

    private function seedEarners(int $count): void
    {
        $organization = Organization::factory()->create();

        for ($i = 0; $i < $count; $i++) {
            $this->userWhoEarned(10 + $i, ['organization_id' => $organization->id]);
        }
    }

    /** Queries run by both leaderboards together. */
    private function countQueries(): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->assertNotEmpty($this->reporting->userLeaderboard());
        $this->assertNotEmpty($this->reporting->organizationLeaderboard());

        DB::disableQueryLog();

        return count(DB::getQueryLog());
    }
}
