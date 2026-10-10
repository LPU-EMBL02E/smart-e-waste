<?php

namespace App\Modules\Deposits\Http\Controllers\Admin;

use App\Modules\Reporting\Services\ReportingQueries;
use Illuminate\View\View;

/**
 * GET /admin/sessions/review — ACCEPTED sessions whose `complete` call never
 * arrived within the review threshold. API_Design.md §7.4.
 *
 * These are the sessions where the item is probably in the bin but no points
 * were credited: the bin lost the server, or lost power, between the deposit
 * and the complete call. They are never expired silently, because the deposit
 * may have physically happened (System_Plan.md §5.3 rule 6).
 *
 * The firmware is expected to resend a saved `complete` when it can, including
 * after a reboot, so a session listed here may still resolve itself.
 *
 * TODO(team): API_Design.md §10 question 1 — the plan defines no ACTION for a
 * session in review; this page lists them only. Decide what an admin should be
 * able to do: credit it manually, mark it failed, or leave it to the firmware's
 * retry. Note that an ADJUSTMENT ledger entry type is already reserved for a
 * manual correction (Database_Schema.md §9).
 *
 * TODO: method body.
 */
class SessionReviewController
{
    public function __construct(private ReportingQueries $reporting) {}

    public function index(): View
    {
        throw new \LogicException('Not implemented.');
    }
}
