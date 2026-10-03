<?php

namespace App\Modules\Rewards\Support;

/**
 * The four kinds of point_ledger row. Database_Schema.md §9.
 *
 * | type       | written when                                      | links to          | delta    |
 * |------------|---------------------------------------------------|-------------------|----------|
 * | EARN       | a deposit completes                               | transaction_id    | positive |
 * | REDEEM     | a reward is redeemed                              | redemption_id     | negative |
 * | REVERSAL   | a transaction is voided, or a redemption cancelled| either one        | opposite |
 * | ADJUSTMENT | reserved for manual corrections; no page writes it| neither           | either   |
 *
 * Only PointsService writes these rows (System_Plan.md §4, boundary rule 1).
 */
enum LedgerEntryType: string
{
    case Earn = 'EARN';
    case Redeem = 'REDEEM';
    case Reversal = 'REVERSAL';
    case Adjustment = 'ADJUSTMENT';
}
