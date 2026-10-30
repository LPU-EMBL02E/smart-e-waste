<?php

namespace Tests\Feature\Notifications;

use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Models\Notification;
use App\Modules\Notifications\Support\NotificationType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The notification list and mark-as-read, for students and admins.
 * API_Design.md §7.3, §7.4, §7.1; Database_Schema.md §12.
 */
class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->create();
        $this->actingAs($this->student);
    }

    public function test_it_lists_only_the_users_own_notifications(): void
    {
        $older = Notification::factory()->for($this->student)->read()->create(['title' => 'Older one']);
        $newer = Notification::factory()->for($this->student)->create(['title' => 'Newer one']);
        Notification::factory()->create(['title' => 'Someone else']);

        $this->get(route('notifications.index'))
            ->assertOk()
            ->assertSeeInOrder([$newer->title, $older->title])
            ->assertDontSee('Someone else')
            ->assertSee(route('notifications.read', $newer))
            ->assertDontSee(route('notifications.read', $older));
    }

    public function test_it_marks_a_notification_as_read(): void
    {
        $notification = Notification::factory()->for($this->student)->create();
        $this->travelTo(Carbon::parse('2026-10-30 01:00:00', 'UTC'));

        $this->from(route('notifications.index'))
            ->post(route('notifications.read', $notification))
            ->assertRedirect(route('notifications.index'));

        $this->assertEquals(now(), $notification->fresh()->read_at);

        // Reading it again changes nothing.
        $this->travel(5)->minutes();
        $this->post(route('notifications.read', $notification))->assertRedirect();

        $this->assertEquals(Carbon::parse('2026-10-30 01:00:00', 'UTC'), $notification->fresh()->read_at);
    }

    public function test_it_does_not_let_a_user_mark_someone_elses_notification(): void
    {
        $someoneElses = Notification::factory()->create();

        $this->post(route('notifications.read', $someoneElses))->assertNotFound();

        $this->assertFalse($someoneElses->fresh()->isRead());
    }

    public function test_it_shows_admins_their_bin_alerts_on_the_same_page(): void
    {
        $admin = User::factory()->admin()->create();
        Notification::factory()->for($admin)->create([
            'type' => NotificationType::BinFull,
            'title' => 'Bin full',
            'message' => 'Bin 1 is 95% full and is refusing deposits until it is emptied.',
        ]);

        $this->actingAs($admin)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Bin 1 is 95% full')
            ->assertSee(route('admin.dashboard'));
    }
}
