<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Models\User;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Middleware\SetCacheHeaders;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Tests\TestCase;

/**
 * Signed-in pages are not kept by the browser, and signing out clears what it
 * stored. API_Design.md §7.1, P55; OWASP ASVS 5.0 V14.3.2.
 */
class SignedInPageCachingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_no_store_on_every_signed_in_route(): void
    {
        // The middleware groups reach the router when the HTTP kernel starts.
        app(Kernel::class);
        $router = app(Router::class);

        $signedIn = collect($router->getRoutes()->getRoutes())
            ->filter(fn (Route $route): bool => in_array(Authenticate::class, $router->gatherRouteMiddleware($route), true));

        $this->assertNotEmpty($signedIn);

        foreach ($signedIn as $route) {
            $this->assertContains(
                SetCacheHeaders::class.':no_store',
                $router->gatherRouteMiddleware($route),
                "{$route->uri()} is signed in but may be cached.",
            );
        }
    }

    public function test_it_tells_the_browser_not_to_keep_a_students_pages(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('home'))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->get(route('profile.show'))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_it_tells_the_browser_not_to_keep_an_admins_pages_or_the_live_summary(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('admin.dashboard'))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->getJson(route('admin.dashboard.summary'))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_it_tells_the_browser_not_to_keep_the_confirmation_page(): void
    {
        $this->actingAs(User::factory()->unconfirmed()->create())
            ->get(route('verification.notice'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_it_clears_the_browsers_stored_data_but_not_cookies_on_sign_out(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('logout'))
            ->assertRedirect(route('login'))
            ->assertHeader('Clear-Site-Data', '"cache", "storage"');
    }
}
