<?php

/**
 * Intentionally empty.
 *
 * Every web route lives in its owning module's routes/web.php and is loaded by
 * App\Providers\ModuleServiceProvider (System_Plan.md §4). Keeping this file
 * empty means there is exactly one place to look for a route: the module that
 * owns the data behind it.
 *
 * Laravel ships this file with a `/` route returning the welcome view. That
 * route is replaced by Identity's RootController, which redirects by role.
 *
 * Run `php artisan route:list` to see every route with its module's middleware
 * applied.
 */
