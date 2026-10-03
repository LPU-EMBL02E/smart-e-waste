<?php

namespace App\Modules\Reporting\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * GET /admin/reports. API_Design.md §7.4.
 *
 * TODO(team): API_Design.md §10 question 2 — the plan names a reports page and
 * scheduled reports but does not say what either contains. Decide the contents
 * against the project guidelines before building this: Step 13 names the
 * technical tests whose data has to come from somewhere, and Step 15 lists the
 * report sections the final document needs.
 *
 * Candidates the data already supports: deposits and weight over a date range,
 * per-organization totals, voided transactions with reasons, bin fill history,
 * redemption fulfilment, and the ACCEPTED-session review list.
 *
 * TODO: method body.
 */
class ReportController
{
    public function __invoke(Request $request): View
    {
        throw new \LogicException('Not implemented.');
    }
}
