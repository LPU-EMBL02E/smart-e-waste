<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Models\User;
use DOMDocument;
use DOMNode;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
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
        $response = $this->actingAs(User::factory()->create())
            ->get(route('home'))->assertOk();

        $this->assertLink($response, 'profile.show', 'My profile');
    }

    public function test_it_shows_a_student_no_admin_link(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('home'));

        $this->assertLink($response, 'admin.dashboard', 'Admin', false);
    }

    public function test_it_keeps_a_student_out_of_the_admin_landing_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_it_links_an_admin_to_the_account_pages_from_the_dashboard(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.dashboard'))->assertOk();

        $this->assertLink($response, 'admin.users.index', 'Users');
        $this->assertLink($response, 'admin.organizations.index', 'Organizations');
        $this->assertLink($response, 'home', 'Student view');
    }

    public function test_it_lets_an_admin_use_the_student_view_and_come_back(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('home'))->assertOk();

        $this->assertLink($response, 'profile.show', 'My profile');
        $this->assertLink($response, 'admin.dashboard', 'Admin');
    }

    public function test_it_escapes_account_names_in_the_page_title_and_header(): void
    {
        $user = User::factory()->create(['first_name' => '<img src=x onerror=alert(1)>']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.users.edit', $user))
            ->assertOk()
            ->assertSee(e($user->fullName()), false)
            ->assertDontSee($user->first_name, false);
    }

    /** Verify destinations and labels without coupling to layout whitespace (DESIGN.md §10). */
    private function assertLink(TestResponse $response, string $route, string $label, bool $present = true): void
    {
        $document = new DOMDocument;
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $links = (new DOMXPath($document))->query('//a[@href="'.route($route).'"]');

        if (! $present) {
            $this->assertCount(0, $links);

            return;
        }

        $labels = array_map(fn (DOMNode $link): string => trim($link->textContent), iterator_to_array($links));
        $this->assertContains($label, $labels);
    }
}
