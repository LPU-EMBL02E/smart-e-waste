<?php

namespace App\Modules\Identity\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The admin role check. API_Design.md §7.1 and §7.4.
 *
 * Applied to EVERY route under /admin, alongside `auth`. A student who opens an
 * admin route gets 403, not a redirect, so the existence of the page is not
 * hidden but the data is never exposed.
 *
 * Registered as the `admin` alias in bootstrap/app.php.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);

        return $next($request);
    }
}
