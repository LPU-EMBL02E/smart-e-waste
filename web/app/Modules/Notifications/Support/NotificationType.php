<?php

namespace App\Modules\Notifications\Support;

/**
 * The notification types. API_Design.md §7.7, P40 and P43.
 *
 * Each one is written by a listener reacting to a module event. Modules announce
 * events; they never call Notifications (System_Plan.md §4, boundary rule 3).
 */
enum NotificationType: string
{
    /** DepositCompleted -> the depositing student. */
    case DepositCompleted = 'DEPOSIT_COMPLETED';

    /** RedemptionFulfilled -> the redeeming student. */
    case RedemptionFulfilled = 'REDEMPTION_FULFILLED';

    /** BinFull -> one row per admin user. */
    case BinFull = 'BIN_FULL';

    /** BinFault -> one row per admin user. */
    case BinFault = 'BIN_FAULT';
}
