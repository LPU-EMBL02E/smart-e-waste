# Blade views

Pages are Blade templates. Forms are plain `method="POST"` forms with `@csrf`,
and a `PUT` route is reached with `@method('PUT')` inside the form. There is no
AJAX-driven CRUD and no Chart.js — both were cut on purpose
(`docs/System_Plan.md` §3).

The one piece of JavaScript in the whole application is the admin dashboard's
polling script, which calls `GET /admin/dashboard/summary` every
`config('ewaste.dashboard_poll_seconds')` seconds.

## Naming

Views are namespaced by module so a page is easy to trace back to the module
that owns its data:

```
layouts/app.blade.php          signed-in student shell
layouts/admin.blade.php        admin shell
layouts/guest.blade.php        login and password pages

identity/auth/login.blade.php
identity/auth/forgot-password.blade.php
identity/auth/reset-password.blade.php
identity/profile/show.blade.php
identity/admin/users/{index,create,edit}.blade.php
identity/admin/organizations/{index,create,edit}.blade.php

devices/admin/bins/{index,create,show,edit,secret}.blade.php

deposits/transactions/index.blade.php
deposits/admin/transactions/{index,show}.blade.php
deposits/admin/sessions/review.blade.php

rewards/home.blade.php
rewards/rewards/{index,show}.blade.php
rewards/redemptions/index.blade.php
rewards/admin/rewards/{index,create,edit}.blade.php
rewards/admin/redemptions/index.blade.php
rewards/admin/points-rate/show.blade.php

reporting/leaderboard.blade.php
reporting/admin/dashboard.blade.php
reporting/admin/organization-leaderboard.blade.php
reporting/admin/reports.blade.php

notifications/index.blade.php
```

Register the namespaces in `ModuleServiceProvider` with
`$this->loadViewsFrom(...)` if module-local view directories are preferred over
this shared tree. Either works; pick one and keep to it.

## Displaying times

Every timestamp in the database is UTC. Convert for display, never at the
query:

```blade
{{ $transaction->created_at->timezone(config('ewaste.display_timezone'))->format('d M Y, g:ia') }}
```

## Responsiveness

The dashboard has to work on a phone as well as a desktop (guidelines, Step 8).
Tables of transactions and telemetry are the parts that break first — give them
a horizontal scroll container rather than letting the page scroll sideways.
