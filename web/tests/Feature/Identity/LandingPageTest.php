<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The account links on the landing pages. API_Design.md §7.3, §7.4,
 * web/docs/TRACK-A-ACCOUNTS.md step A8. /home and /admin themselves are tested by
 * their owners, in HomeTest and DashboardTest.
 */
class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_links_a_student_to_their_profile_from_home(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('home'))
            ->assertOk()
            ->assertSee($this->link('profile.show', 'Profile'), false);
    }

    public function test_it_shows_a_student_no_admin_link(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('home'))
            ->assertDontSee($this->link('admin.dashboard', 'Admin'), false);
    }

    public function test_it_keeps_a_student_out_of_the_admin_landing_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_it_links_an_admin_to_the_account_pages_from_the_dashboard(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee($this->link('admin.users.index', 'Users'), false)
            ->assertSee($this->link('admin.organizations.index', 'Organizations'), false)
            ->assertSee($this->link('home', 'Student view'), false);
    }

    public function test_it_lets_an_admin_use_the_student_view_and_come_back(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('home'))
            ->assertOk()
            ->assertSee($this->link('profile.show', 'Profile'), false)
            ->assertSee($this->link('admin.dashboard', 'Admin'), false);
    }

    private function link(string $route, string $label): string
    {
        return '<a href="'.route($route).'">'.$label.'</a>';
    }
}
