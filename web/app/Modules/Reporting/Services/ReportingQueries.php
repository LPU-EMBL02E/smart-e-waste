<?php

namespace App\Modules\Reporting\Services;

use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Modules\Rewards\Models\PointLedgerEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Read-only queries behind the admin dashboard, the leaderboards and the
 * reports. System_Plan.md §4: Reporting owns no tables and writes nothing; it
 * reads whatever it needs across modules (boundary rule 2).
 *
 * Nothing else in the application may depend on this class.
 *
 * TODO: all method bodies.
 */
class ReportingQueries
{
    /**
     * GET /admin/dashboard/summary — the polling script's JSON.
     * API_Design.md §7.5. Times in the reply are ISO 8601 in UTC; the script
     * converts them for display.
     *
     *   bins[]                    every device that is not retired, with its
     *                             latest reading. fill_percent and status are
     *                             null when a bin has no reading yet
     *   today.deposits            COMPLETED transactions created since midnight,
     *                             display time zone
     *   today.weight_g            sum of net_weight_g for those transactions'
     *                             sessions
     *   today.points_awarded      sum of points_awarded for those transactions
     *   pending_redemptions       redemptions with status PENDING
     *   sessions_awaiting_review  ACCEPTED sessions older than the review
     *                             threshold
     */
    public function dashboardSummary(): array
    {
        // TODO
        throw new \LogicException('Not implemented.');
    }

    /**
     * The user leaderboard, ranked by POINTS EARNED, not by balance, so
     * redeeming never lowers a rank (System_Plan.md §5.6).
     *
     * Points earned = the EARN ledger rows minus the REVERSAL rows that link to
     * a transaction. Redemption rows are ignored.
     *
     * Includes every user, admins included: those are the only two roles and
     * every user has a QR token (API_Design.md P31).
     *
     * One query: the ledger grouped by user, joined to the users for their
     * names. Each User carries an integer `points_earned`.
     *
     * @return Collection<int, User>
     */
    public function userLeaderboard(int $limit = 50): Collection
    {
        $earned = $this->pointsEarned()
            ->addSelect('point_ledger.user_id')
            ->groupBy('point_ledger.user_id');

        return User::query()
            ->joinSub($earned, 'earned', 'earned.user_id', '=', 'users.id')
            ->orderByDesc('earned.points_earned')
            ->orderBy('users.id')
            ->limit($limit)
            ->withCasts(['points_earned' => 'integer'])
            ->get(['users.id', 'users.first_name', 'users.last_name', 'earned.points_earned']);
    }

    /**
     * The organization leaderboard. Students ONLY: filter on role = 'student'
     * before grouping, or admins — who have no organization_id — produce a NULL
     * group (System_Plan.md §5.6).
     *
     * An organization's score is the total points earned by the students
     * CURRENTLY in it, so the ledger is grouped by the users' organization_id
     * as it is now.
     *
     * One query. Each Organization carries an integer `points_earned`.
     *
     * @return Collection<int, Organization>
     */
    public function organizationLeaderboard(): Collection
    {
        $earned = $this->pointsEarned()
            ->join('users', 'users.id', '=', 'point_ledger.user_id')
            ->where('users.role', User::ROLE_STUDENT)
            ->addSelect('users.organization_id')
            ->groupBy('users.organization_id');

        return Organization::query()
            ->joinSub($earned, 'earned', 'earned.organization_id', '=', 'organizations.id')
            ->orderByDesc('earned.points_earned')
            ->orderBy('organizations.id')
            ->withCasts(['points_earned' => 'integer'])
            ->get(['organizations.id', 'organizations.name', 'earned.points_earned']);
    }

    /**
     * TODO(team): API_Design.md §10 question 2 — the plan names a reports page
     * and scheduled reports but does not say what they contain. Decide the
     * contents against the project guidelines (Step 13 test tables, Step 15
     * report sections) before building this.
     */
    public function report(array $filters): array
    {
        throw new \LogicException('Not implemented: report contents are an open question.');
    }

    // ---------------------------------------------------------------- helpers

    /**
     * SUM(points_delta) AS points_earned over the rows that count as earned,
     * for the caller to group. Both leaderboards rank on it.
     *
     * Ties and zero scores, until the team decides otherwise
     * (web/docs/TRACK-D-REPORTING-AND-HISTORY.md, question 2): a score of zero
     * is not listed, and the callers order equal scores by id so the order is
     * stable.
     */
    private function pointsEarned(): Builder
    {
        return PointLedgerEntry::query()
            ->countsAsEarned()
            ->selectRaw('SUM(point_ledger.points_delta) AS points_earned')
            ->having('points_earned', '>', 0);
    }
}
