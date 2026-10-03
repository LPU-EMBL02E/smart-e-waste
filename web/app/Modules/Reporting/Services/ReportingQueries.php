<?php

namespace App\Modules\Reporting\Services;

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
     * every user has a QR token.
     */
    public function userLeaderboard(int $limit = 50): \Illuminate\Support\Collection
    {
        // TODO
        throw new \LogicException('Not implemented.');
    }

    /**
     * The organization leaderboard. Students ONLY: filter on role = 'student'
     * before grouping, or admins — who have no organization_id — produce a NULL
     * group (System_Plan.md §5.6).
     *
     * An organization's score is the total points earned by the students
     * CURRENTLY in it.
     */
    public function organizationLeaderboard(): \Illuminate\Support\Collection
    {
        // TODO
        throw new \LogicException('Not implemented.');
    }

    /**
     * Total collected weight: the sum of net_weight_g over deposits whose
     * transaction is COMPLETED. Voided deposits are excluded
     * (System_Plan.md §5.6).
     */
    public function totalCollectedWeightGrams(): float
    {
        // TODO
        throw new \LogicException('Not implemented.');
    }

    /**
     * ACCEPTED sessions that have waited longer than the review threshold for
     * their `complete` call, for /admin/sessions/review.
     *
     * These are never expired silently, because the item may already be in the
     * bin (System_Plan.md §5.3 rule 6).
     */
    public function sessionsAwaitingReview(): \Illuminate\Support\Collection
    {
        // TODO
        throw new \LogicException('Not implemented.');
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
}
