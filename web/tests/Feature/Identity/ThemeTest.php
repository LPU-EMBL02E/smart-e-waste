<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Support\Theme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Choosing light, dark or the device's theme. API_Design.md §7.2, §7.6
 * ("Choose a theme"), P52; web/docs/DESIGN.md §3, "Dark theme".
 */
class ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_remembers_a_chosen_theme_for_a_year_and_goes_back(): void
    {
        $response = $this->from(route('login'))
            ->post(route('theme.update'), ['theme' => 'dark'])
            ->assertRedirect(route('login'))
            ->assertCookie(Theme::COOKIE, 'dark');

        $expires = $response->getCookie(Theme::COOKIE, false)->getExpiresTime();
        $this->assertEqualsWithDelta(now()->addDays(config('ewaste.theme_cookie_days'))->getTimestamp(), $expires, 5);
    }

    public function test_it_forgets_the_choice_to_follow_the_device(): void
    {
        $this->withCookie(Theme::COOKIE, 'dark')
            ->post(route('theme.update'), ['theme' => 'system'])
            ->assertRedirect()
            ->assertCookieExpired(Theme::COOKIE);
    }

    public function test_it_refuses_an_unknown_theme(): void
    {
        $this->post(route('theme.update'), ['theme' => 'neon'])
            ->assertSessionHasErrors('theme')
            ->assertCookieMissing(Theme::COOKIE);
    }

    public function test_it_marks_the_page_with_the_chosen_theme(): void
    {
        $this->withCookie(Theme::COOKIE, 'dark')
            ->get(route('login'))
            ->assertSee('<html lang="en" data-theme="dark">', false)
            ->assertSee('<meta name="color-scheme" content="dark">', false);
    }

    public function test_it_follows_the_device_without_a_choice(): void
    {
        $this->get(route('login'))
            ->assertDontSee('data-theme=', false)
            ->assertSee('<meta name="color-scheme" content="light dark">', false);
    }

    public function test_it_treats_an_unknown_cookie_as_following_the_device(): void
    {
        $this->withCookie(Theme::COOKIE, 'neon')
            ->get(route('login'))
            ->assertDontSee('data-theme=', false);
    }

    public function test_it_shows_the_control_with_the_current_choice_pressed(): void
    {
        $this->withCookie(Theme::COOKIE, 'light')
            ->actingAs(User::factory()->create())
            ->get(route('home'))
            ->assertSee(route('theme.update'))
            ->assertSee('value="light" aria-pressed="true"', false)
            ->assertSee('value="dark" aria-pressed="false"', false)
            ->assertSee('value="system" aria-pressed="false"', false);
    }

    public function test_it_offers_the_control_on_the_sign_in_and_admin_pages(): void
    {
        $this->get(route('login'))->assertSee(route('theme.update'));

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.dashboard'))
            ->assertSee(route('theme.update'));
    }

    public function test_it_lets_guests_and_unconfirmed_users_choose(): void
    {
        $this->post(route('theme.update'), ['theme' => 'light'])->assertCookie(Theme::COOKIE, 'light');

        $this->actingAs(User::factory()->unconfirmed()->create())
            ->post(route('theme.update'), ['theme' => 'dark'])
            ->assertCookie(Theme::COOKIE, 'dark');
    }
}
