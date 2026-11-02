# Blade views

Pages are Blade templates. Forms are plain `method="POST"` forms with `@csrf`,
and a `PUT` route is reached with `@method('PUT')` inside the form. There is no
AJAX-driven CRUD and no Chart.js — both were cut on purpose
(`docs/System_Plan.md` §3).

The one piece of JavaScript in the whole application is the admin dashboard's
polling script, which calls `GET /admin/dashboard/summary` every
`config('ewaste.dashboard_poll_seconds')` seconds.

## Structure

```
resources/css/
  app.css                 the entry point; imports everything below
  tokens.css              the design system's colours, type, spacing, as @theme tokens
  base.css                element defaults
  components/*.css        one file per component family

resources/views/
  layouts/                base -> portal -> app | admin, and guest
  layouts/partials/       sidebar, navigation, flash messages
  components/ui/          <x-ui.*>: button, pill, meter, tile, focal-panel, panel,
                          table, empty, page-heading, nav-link, icon
  components/form/        <x-form.*>: input, textarea, select, checkbox, field
  vendor/pagination/      the pager
  errors/                 403, 404, 419, 429, 500, 503
  <module>/...            the pages, by the module that owns their data
```

Build a page from these before writing markup of your own. All visual values
come from `web/docs/wireframes/design-system.html` through `tokens.css`: a colour, size or
font written anywhere else is a defect. Sizes outside the tokens don't exist,
because `tokens.css` resets Tailwind's default scales.

## Naming

Views are namespaced by module so a page is easy to trace back to the module
that owns its data:

```
identity/auth/{login,register,forgot-password,reset-password,verify-email}.blade.php
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

## Displaying values

Every timestamp in the database is UTC. Convert for display, never at the
query. `App\Support\Display` holds the one format for each kind of value, and
each has a Blade directive:

```blade
@datetime($transaction->created_at)         {{-- 30 Oct 2026, 9:00am --}}
@datetime($user->last_login_at, 'Never')    {{-- with text for a missing time --}}
@datetimeseconds(...)  @date(...)
@grams(...)  @kilograms(...)  @points(...)  @wholenumber(...)  @percent(...)  @faultlabel(...)
```

`resources/js/pages/admin-dashboard.js` repeats a few of these formats for the
values it redraws, so a change to one changes both.

## Responsiveness

The dashboard has to work on a phone as well as a desktop (guidelines, Step 8).
A wide table sits in `<x-ui.table>`, which scrolls by itself, so the page never
scrolls sideways.
