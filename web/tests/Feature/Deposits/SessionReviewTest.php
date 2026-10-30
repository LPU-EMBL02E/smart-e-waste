<?php

namespace Tests\Feature\Deposits;

use App\Modules\Deposits\Models\DepositSession;
use App\Modules\Identity\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ACCEPTED sessions past the review threshold, at /admin/sessions/review.
 * API_Design.md §7.4; System_Plan.md §5.3 rule 6 and §6.3.
 */
class SessionReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_it_lists_accepted_sessions_past_the_threshold(): void
    {
        $older = $this->acceptedMinutesAgo(30);
        $old = $this->acceptedMinutesAgo(config('ewaste.review_threshold_minutes') + 1);

        $this->get(route('admin.sessions.review'))
            ->assertOk()
            ->assertSeeInOrder([$older->id, $old->id])
            ->assertSee($old->user->fullName())
            ->assertSee($old->device->name);

        $this->assertTrue($old->isAwaitingReview());
    }

    public function test_it_leaves_out_recent_accepted_sessions_and_other_statuses(): void
    {
        $recent = $this->acceptedMinutesAgo(config('ewaste.review_threshold_minutes') - 1);
        $this->travel(-30)->minutes();
        DepositSession::factory()->completed()->create();
        DepositSession::factory()->failed()->create();
        DepositSession::factory()->stale()->create();
        $this->travelBack();

        $this->get(route('admin.sessions.review'))
            ->assertOk()
            ->assertDontSee($recent->id)
            ->assertViewHas('sessions', fn (LengthAwarePaginator $page) => $page->total() === 0);

        $this->assertFalse($recent->isAwaitingReview());
    }

    public function test_it_keeps_students_out_of_the_review_page(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.sessions.review'))->assertForbidden();
    }

    private function acceptedMinutesAgo(int $minutes): DepositSession
    {
        $this->travel(-$minutes)->minutes();
        $session = DepositSession::factory()->accepted()->create();
        $this->travelBack();

        return $session;
    }
}
