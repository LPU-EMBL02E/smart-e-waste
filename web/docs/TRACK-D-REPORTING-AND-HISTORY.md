# Track D — Reporting and history

Build spec for the read side: the student's home page and history, the
leaderboards, the admin dashboard with its live summary, the session review
list and the notifications page. It runs in parallel with
[Track A](TRACK-A-ACCOUNTS.md) and [Track C](TRACK-C-REDEMPTIONS-AND-VOIDS.md).

The rules come from `docs/`, which wins over this sheet if the two ever disagree:

- `System_Plan.md` §4 (boundaries), §5.6 (pages, leaderboards, collected weight)
- `API_Design.md` §7.1 (conventions), §7.3–§7.5 (routes and the live summary),
  §10 questions 1 and 2, P31, P34
- `Database_Schema.md` §6, §8, §9, §12 and "Additional Indexes"

This track **writes nothing** except `notifications.read_at`. Its risk is not
wrong points but slow pages: the server is a Raspberry Pi 4, so every query
below is written for it (AGENTS.md §5.1).

**Logic first, interface later.** Every page here is bare Blade; the design pass
comes later.

---

## Module boundaries

| Module | Pages in this track | Reads through |
|---|---|---|
| Rewards | `/home` | `PointsService::balanceFor()`, `DepositService` |
| Deposits | `/transactions`, `/admin/sessions/review` | `DepositService` |
| Reporting | `/leaderboard`, `/admin`, `/admin/dashboard/summary`, `/admin/leaderboard/organizations`, `/admin/reports` | `ReportingQueries` |
| Notifications | `/notifications` | The `Notification` model |

Rules for this track:

- **Reporting owns no tables and writes nothing** (`System_Plan.md` §4). It reads
  across modules directly, and **nothing outside Reporting depends on
  `ReportingQueries`**.
- **One rule, one place.** A filter that two pages need is a model scope in the
  module that owns the table, and both pages use it:
  `PointLedgerEntry::countsAsEarned()` (Track C, step C1),
  `Transaction::completed()` (Track C, step C5) and
  `DepositSession::awaitingReview()` (step D3 here).
- **Controllers are thin.** List reads are service methods that return a
  paginator, the pattern of `BinService::list()` and
  `RewardRuleService::history()`.
- **No N+1.** Aggregate in the query (`sum`, `count`, `groupBy`), eager-load with
  `with()`, and never query inside a loop or a Blade template.
- **Paginate every list.** The leaderboard is a ranked top list with a fixed
  limit, not a full table.
- **A user sees only their own** transactions and notifications
  (`API_Design.md` §7.1): read them through `$request->user()`, never by id.
- Stored times are UTC. "Today" means since midnight in
  `config('ewaste.display_timezone')`, converted to UTC for the query, the way
  `PointsService::pointsEarnedToday()` does it.

---

## Step D0 — Track D's test setup

1. **`database/factories/Notifications/NotificationFactory.php`**, with a
   `read()` state.
2. Use the factories from Tracks B and C for everything else. Points for a test
   user come from `PointLedgerEntryFactory` (step C0), so Track D's leaderboard
   tests wait for C0 and C1.

---

## Step D1 — The student's home and history (Rewards, Deposits)

| Method | Rule |
|---|---|
| `DepositService::transactionsFor(User $user): LengthAwarePaginator` | The user's transactions through `deposit_sessions.user_id`, newest first, with the session eager-loaded. Voided ones are shown, with their status |

- `HomeController` (`/home`): the balance from `PointsService::balanceFor()`,
  and the most recent transactions from the same method. A balance below zero is
  shown as it is.
- `TransactionController` (`/transactions`): the full paginated list.

Tests (`tests/Feature/Rewards/HomeTest.php`,
`tests/Feature/Deposits/TransactionHistoryTest.php`):
- `test_it_shows_the_balance_from_the_ledger`
- `test_it_shows_a_negative_balance_after_a_void`
- `test_it_lists_only_the_users_own_transactions`
- `test_it_paginates_the_history`

---

## Step D2 — Leaderboards (Reporting)

Both rank by **points earned**, not balance: `EARN` rows minus the `REVERSAL`
rows that link to a transaction, using `PointLedgerEntry::countsAsEarned()`. One
grouped query each; never a call to `pointsEarnedBy()` per user.

| Method | Rule |
|---|---|
| `userLeaderboard(int $limit = 50): Collection` | Every user, admins included (P31): group the ledger by `user_id`, sum, order by the sum, limit. Eager-load the users' names in one query |
| `organizationLeaderboard(): Collection` | Students only: filter `role = 'student'` **before** grouping, join to `organizations`, group by organization. The score is the total for the students currently in it |

- `LeaderboardController` (`/leaderboard`) and
  `OrganizationLeaderboardController` (`/admin/leaderboard/organizations`) pass
  the result to the view.

Tests (`tests/Feature/Reporting/LeaderboardTest.php`):
- `test_it_ranks_users_by_points_earned_not_balance`
- `test_it_does_not_lower_a_rank_when_a_user_redeems`
- `test_it_drops_a_voided_deposit_from_the_ranking`
- `test_it_includes_admins_on_the_user_leaderboard`
- `test_it_counts_only_students_on_the_organization_leaderboard`
- `test_it_scores_an_organization_by_its_current_students`
- `test_it_runs_a_fixed_number_of_queries_however_many_users` (count queries
  with `DB::listen` or `DB::getQueryLog()` for 3 users and for 30)

---

## Step D3 — Sessions awaiting review (Deposits)

- New scope `DepositSession::scopeAwaitingReview()`: status `ACCEPTED` and
  `created_at` older than `config('ewaste.review_threshold_minutes')`, the same
  rule as the model's existing `isAwaitingReview()`.
- `DepositService::sessionsAwaitingReview(): LengthAwarePaginator`, oldest
  first, with user and device eager-loaded.
- `SessionReviewController` calls `DepositService` instead of
  `ReportingQueries`. Remove `ReportingQueries::sessionsAwaitingReview()` in the
  same commit; the dashboard counts with the scope instead.
- The page **lists only**. What an admin can do with such a session is open
  (`API_Design.md` §10 q1); leave the `TODO(team)` in the controller.

Tests (`tests/Feature/Deposits/SessionReviewTest.php`):
- `test_it_lists_accepted_sessions_past_the_threshold`
- `test_it_leaves_out_recent_accepted_sessions_and_other_statuses`
- `test_it_keeps_students_out_of_the_review_page`

---

## Step D4 — The admin dashboard and live summary (Reporting)

`ReportingQueries::dashboardSummary(): array` returns exactly the body in
`API_Design.md` §7.5:

| Field | Query |
|---|---|
| `generated_at` | Now, ISO 8601 UTC |
| `bins[]` | Devices whose status is not `retired`, with `latestTelemetry` eager-loaded (one query for all bins). The four reading fields are null when a bin has no reading |
| `today` | One query: `COMPLETED` transactions since local midnight joined to their sessions, with `count`, `sum(net_weight_g)` and `sum(points_awarded)` |
| `pending_redemptions` | `count` of `PENDING` redemptions |
| `sessions_awaiting_review` | `DepositSession::awaitingReview()->count()` |

Every time in the reply is ISO 8601 in UTC.

`DashboardController`:
- `summary` returns the array as JSON. It uses the admin's normal session, not
  the device signature.
- `index` renders the page with the same array, so the page is complete without
  JavaScript.

**The one script.** A short inline `<script>` in
`reporting/admin/dashboard.blade.php`, plain JavaScript with no library and no
build step: `fetch()` the summary every
`config('ewaste.dashboard_poll_seconds')` seconds (P34), update the numbers and
the bin rows, and convert times for display. No other page polls.

Tests (`tests/Feature/Reporting/DashboardTest.php`):
- `test_it_returns_the_summary_fields_exactly`
- `test_it_leaves_out_retired_bins`
- `test_it_returns_nulls_for_a_bin_with_no_reading`
- `test_it_counts_today_from_manila_midnight`
- `test_it_leaves_voided_transactions_out_of_today`
- `test_it_returns_utc_times`
- `test_it_keeps_students_out_of_the_summary`

---

## Step D5 — Notifications (Notifications)

- `NotificationController::index`: the user's own notifications, newest first,
  paginated.
- `NotificationController::markRead`: look the notification up through
  `$request->user()->notifications()`, so another user's notification is not
  found; set `read_at` once, through a new `Notification::markAsRead()`; redirect
  back. Reading an already-read one changes nothing.

Tests (`tests/Feature/Notifications/NotificationTest.php`):
- `test_it_lists_only_the_users_own_notifications`
- `test_it_marks_a_notification_as_read`
- `test_it_does_not_let_a_user_mark_someone_elses_notification`
- `test_it_shows_admins_their_bin_alerts_on_the_same_page`

---

## Step D6 — Reports (blocked)

What the reports page contains is open (`API_Design.md` §10 q2). Leave
`ReportingQueries::report()` and `ReportController` throwing, with their
`TODO(team)`. Don't build a page until the team decides the contents from
`PROJECT-GUIDELINES.md`.

---

## Questions for the owner

1. **Home page length.** How many recent transactions does `/home` show? The
   docs say "recent". Once decided, the number goes in `config/ewaste.php`.
2. **Leaderboard ties and zeros.** How are equal scores ranked, and are users
   and organizations with no points listed at all? Until decided, ties are
   ordered by id only so the order is stable, and zero scores are not listed.

## Views

Bare Blade from `resources/views/README.md`: `rewards/home`,
`deposits/transactions/index`, `deposits/admin/sessions/review`,
`reporting/leaderboard`, `reporting/admin/dashboard`,
`reporting/admin/organization-leaderboard` and `notifications/index`. Plain
tables, no styling. The dashboard's polling script is the only JavaScript in
the application. Times are converted to `config('ewaste.display_timezone')` in
the view.

## Done when

- Every step's tests pass, plus `php artisan test`, `./vendor/bin/pint --test`,
  `../scripts/check-swappability.sh` and
  `python3 ../tools/simulator/test_vectors.py`.
- Every list is paginated or limited, and the query-count test in D2 passes.
- Nothing outside Reporting uses `ReportingQueries`, and Reporting writes
  nothing.
- No controller contains a query or a rule.
