<?php

namespace App\Modules\Reporting\Services;

use App\Modules\Deposits\Models\DepositSession;
use App\Modules\Deposits\Models\Transaction;
use App\Modules\Devices\Models\Device;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Modules\Rewards\Models\PointLedgerEntry;
use App\Modules\Rewards\Models\Redemption;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Read-only queries behind the admin dashboard, the leaderboards and the
 * reports. System_Plan.md §4: Reporting owns no tables and writes nothing; it
 * reads whatever it needs across modules (boundary rule 2).
 *
 * Nothing else in the application may depend on this class.
 */
class ReportingQueries
{
    /**
     * GET /admin/dashboard/summary — the polling script's JSON.
     * API_Design.md §7.5. Times in the reply are ISO 8601 in UTC; the script
     * converts them for display.
     *
     *   bins[]                    every device that is not retired, with its
     *                             latest reading. fill_percent, status,
     *                             panel_open and fault_code are null when a
     *                             bin has no reading yet
     *   today.deposits            COMPLETED transactions created since midnight,
     *                             display time zone
     *   today.weight_g            sum of net_weight_g for those transactions'
     *                             sessions
     *   today.points_awarded      sum of points_awarded for those transactions
     *   pending_redemptions       redemptions with status PENDING
     *   sessions_awaiting_review  ACCEPTED sessions older than the review
     *                             threshold
     *
     * Five queries however many bins: the bins and their latest readings, one
     * aggregate for today, and two counts.
     *
     * @return array{generated_at: string, bins: list<array{id: int, name: string, fill_percent: ?float, status: ?string, panel_open: ?bool, fault_code: ?string, last_seen_at: ?string}>, today: array{deposits: int, weight_g: float, points_awarded: int}, pending_redemptions: int, sessions_awaiting_review: int}
     */
    public function dashboardSummary(): array
    {
        return [
            'generated_at' => $this->utc(now()),
            'bins' => $this->binSummaries(),
            'today' => $this->todaySummary(),
            'pending_redemptions' => Redemption::where('status', Redemption::STATUS_PENDING)->count(),
            'sessions_awaiting_review' => DepositSession::awaitingReview()->count(),
        ];
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
     * Every bin that is not retired, with its latest reading eager-loaded in
     * one query for all of them. The four reading fields are null before a
     * bin's first reading (API_Design.md §7.5).
     */
    private function binSummaries(): array
    {
        return Device::query()
            ->where('status', '!=', Device::STATUS_RETIRED)
            ->with('latestTelemetry')
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name', 'last_seen_at'])
            ->map(function (Device $device) {
                $reading = $device->latestTelemetry;

                return [
                    'id' => $device->id,
                    'name' => $device->name,
                    'fill_percent' => $reading === null ? null : (float) $reading->fill_percent,
                    'status' => $reading?->status,
                    'panel_open' => $reading?->panel_open,
                    'fault_code' => $reading?->fault_code?->value,
                    'last_seen_at' => $this->utc($device->last_seen_at),
                ];
            })
            ->all();
    }

    /**
     * COMPLETED transactions since midnight in the display time zone, converted
     * to UTC for the query the way PointsService::pointsEarnedToday() does.
     * Voided ones drop out. One aggregate over the created_at index, joined to
     * the sessions for their weight.
     */
    private function todaySummary(): array
    {
        $localMidnight = now(config('ewaste.display_timezone'))->startOfDay()->utc();

        $today = Transaction::query()
            ->completed()
            ->join('deposit_sessions', 'deposit_sessions.id', '=', 'transactions.session_id')
            ->where('transactions.created_at', '>=', $localMidnight)
            ->selectRaw('COUNT(*) AS deposits')
            ->selectRaw('SUM(deposit_sessions.net_weight_g) AS weight_g')
            ->selectRaw('SUM(transactions.points_awarded) AS points_awarded')
            ->toBase()
            ->first();

        // SUM is NULL when nothing matched; the casts make that 0.
        return [
            'deposits' => (int) $today->deposits,
            'weight_g' => round((float) $today->weight_g, 2),
            'points_awarded' => (int) $today->points_awarded,
        ];
    }

    /** ISO 8601 in UTC, e.g. 2026-10-03T01:39:00Z; the script converts it for display. */
    private function utc(?CarbonInterface $time): ?string
    {
        return $time?->toIso8601ZuluString();
    }

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
