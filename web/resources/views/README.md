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

## Shared presentation

The guest shell and the shared authenticated shell (`layouts/portal.blade.php`)
load compiled CSS through `layouts/partials/head.blade.php`. Admin and student
layouts select the existing navigation routes through one shared partial. The
palette and component styles live in `resources/css/app.css`, mapped into the
existing Tailwind theme from `web/docs/DESIGN.md`. Build with `npm ci` followed
by `npm run build` from `web/`; generated assets remain untracked.

The navigation icons reuse the outline paths in the student HTML prototype through
`layouts/partials/icon.blade.php`. Both portals share the bell, account initials,
navigation spacing and panel styles. Dashboard presentation follows the HTML
wireframes using the current controller data; prototype-only rankings, feeds and
unread counts are not substituted with sample values.

Forms retain their existing POST/CSRF contracts, field names and old input.
Field errors are linked to their controls; flash messages persist after redirects.
`pagination/portal.blade.php` uses Laravel's supplied pagination elements.
The dashboard keeps its existing polling interval and shows a small refresh warning
while preserving the last confirmed readings. Reports stay unavailable in navigation
until their contents are agreed; this presentation change implements no report logic.

For a browser check, run `tests/browser/frontend.check.js` through Playwright CLI's
`run-code --filename` against an isolated, populated loopback preview. It expects
the synthetic `admin@example.test` and `student@example.test` preview accounts
(password `Preview-2026!`), more than one page of users, and an active reward with
stock. Use existing module factories in a separate QA database; never a shared or
production database. The checker exercises both roles, responsive pages, pagination,
validation, skip navigation and dashboard refresh failure/recovery. It does not
seed records or submit business operations.

Web permission, missing-page, expired-page and server-error templates share the same
visual system in `errors/`; their HTTP statuses and device JSON responses are unchanged.
PHP feature tests use Laravel's `withoutVite()` so they can run before assets are
built in CI. The separate build and browser checks verify the compiled assets.
