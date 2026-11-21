<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The sign-in pages are not kept by the browser. API_Design.md §7.2, P53;
 * OWASP ASVS 5.0 V14.3.2.
 */
class GuestPageCachingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_tells_the_browser_not_to_keep_the_guest_pages(): void
    {
        $pages = [
            route('register'),
            route('login'),
            route('password.request'),
            route('password.reset', ['token' => 'the-token', 'email' => 'ana@example.com']),
        ];

        foreach ($pages as $page) {
            $this->get($page)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        }
    }

    public function test_it_leaves_the_guest_form_redirects_with_the_default_header(): void
    {
        $this->from(route('login'))
            ->post(route('login'), ['email' => 'nobody@example.com', 'password' => 'wrong-password'])
            ->assertRedirect(route('login'))
            ->assertHeader('Cache-Control', 'no-cache, private');
    }

    public function test_it_sends_a_signed_in_user_away_when_a_guest_page_is_asked_for_again(): void
    {
        $this->actingAs(User::factory()->unconfirmed()->create())
            ->get(route('register'))
            ->assertRedirect(route('home'));

        $this->actingAs(User::factory()->create())
            ->get(route('login'))
            ->assertRedirect(route('home'));
    }
}
