<?php

namespace App\Modules\Reporting\Http\Controllers;

use App\Modules\Reporting\Services\ReportingQueries;
use Illuminate\View\View;

/**
 * GET /leaderboard — the user leaderboard. API_Design.md §7.3.
 *
 * Ranked by POINTS EARNED, not by current balance, so redeeming a reward never
 * lowers a rank (System_Plan.md §5.6). Includes every user, admins included.
 *
 * TODO: method body.
 */
class LeaderboardController
{
    public function __construct(private ReportingQueries $reporting)
    {
    }

    public function __invoke(): View
    {
        throw new \LogicException('Not implemented.');
    }
}
