<?php

/**
 * Scheduled work. System_Plan.md §3 and §7, step 7.
 *
 * The host needs exactly ONE cron entry for all of this:
 *
 *   * * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
 *
 * QUEUE_CONNECTION is `sync`, so there is no worker process to run. Keep every
 * scheduled task short enough to finish inside a minute.
 */

use App\Modules\Deposits\Services\DepositService;
use Illuminate\Support\Facades\Schedule;

/**
 * The session timeout sweep.
 *
 * Moves OPEN sessions past expires_at to EXPIRED. ACCEPTED sessions are never
 * touched — the item may already be in the bin, so they wait for `complete` and
 * appear on /admin/sessions/review instead (System_Plan.md §5.3 rule 6).
 *
 * Device calls also expire a stale session at request time (P12), so this sweep
 * is housekeeping, not correctness: a bin that never calls again must not leave
 * a session OPEN forever.
 */
Schedule::call(function (DepositService $deposits) {
    $deposits->expireOpenSessions();
})->everyMinute()->name('deposit-sessions:expire')->withoutOverlapping();

/**
 * TODO(team): scheduled reports. API_Design.md §10 question 2 — the plan names
 * them but does not say what they contain or how often they run. Decide the
 * contents first (see Reporting\Services\ReportingQueries::report()).
 */
