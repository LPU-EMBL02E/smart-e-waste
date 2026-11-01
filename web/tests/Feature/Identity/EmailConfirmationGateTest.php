<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Models\User;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Tests\TestCase;

/**
 * Only a confirmed user reaches a signed-in page.
 * API_Design.md §7.2–§7.4, P48, web/docs/TRACK-A-ACCOUNTS.md step A1.
 */
class EmailConfirmationGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_redirects_an_unconfirmed_user_to_the_confirmation_page(): void
    {
        $this->actingAs(User::factory()->unconfirmed()->create())
            ->get(route('home'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_it_lets_a_confirmed_user_reach_home(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('home'))
            ->assertOk();
    }

    /**
     * Each module declares its own routes, so this reads the whole route table:
     * a module that forgets the gate fails here, not in production.
     */
    public function test_it_gates_every_signed_in_route_except_logout_and_the_notice(): void
    {
        // Resolving the HTTP kernel is what gives the router its middleware aliases
        // and groups; this test makes no request that would do it.
        $this->app->make(Kernel::class);
        $router = $this->app->make(Router::class);

        $ungated = collect($router->getRoutes()->getRoutes())
            ->filter(function (Route $route) use ($router): bool {
                $middleware = $router->gatherRouteMiddleware($route);

                return in_array(Authenticate::class, $middleware, true)
                    && ! in_array(EnsureEmailIsVerified::class, $middleware, true);
            })
            ->map(fn (Route $route): ?string => $route->getName())
            ->values()
            ->all();

        $this->assertEqualsCanonicalizing(['logout', 'verification.notice'], $ungated);
    }
}
