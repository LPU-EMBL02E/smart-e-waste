<?php

namespace App\Modules\Reporting\Http\Controllers\Admin;

use App\Modules\Reporting\Services\ReportingQueries;
use Illuminate\View\View;

/**
 * GET /admin/leaderboard/organizations. API_Design.md §7.4.
 *
 * Counts STUDENTS ONLY. Admins have no organization_id, so the query filters on
 * role = 'student' before grouping, or a NULL organization group appears
 * (System_Plan.md §5.6).
 *
 * An organization's score is the total points earned by the students currently
 * in it, so moving a student between organizations moves their points too.
 *
 * TODO: method body.
 */
class OrganizationLeaderboardController
{
    public function __construct(private ReportingQueries $reporting) {}

    public function __invoke(): View
    {
        throw new \LogicException('Not implemented.');
    }
}
