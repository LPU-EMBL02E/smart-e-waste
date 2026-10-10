<?php

namespace App\Modules\Deposits\Http\Controllers\Admin;

use App\Modules\Deposits\Http\Requests\Admin\VoidTransactionRequest;
use App\Modules\Deposits\Models\Transaction;
use App\Modules\Deposits\Services\DepositService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Admin transaction records. API_Design.md §7.4.
 *
 *   GET  /admin/transactions                      list + total collected weight
 *   GET  /admin/transactions/{transaction}        one, with its session's
 *                                                 weight, verification and
 *                                                 actuator values
 *   POST /admin/transactions/{transaction}/void   void it
 *
 * Total collected weight is the sum of net_weight_g over deposits whose
 * transaction is COMPLETED; voided deposits are excluded
 * (System_Plan.md §5.6).
 *
 * The show page is the evidence trail for one deposit — weight, sample count,
 * stability, verification method, pass/fail, score, actuator result and cycle
 * time — which is what makes the testing tables in the guidelines (Step 13)
 * possible to fill from real data.
 *
 * TODO: all method bodies.
 */
class TransactionController
{
    public function __construct(private DepositService $deposits) {}

    public function index(): View
    {
        throw new \LogicException('Not implemented.');
    }

    public function show(Transaction $transaction): View
    {
        throw new \LogicException('Not implemented.');
    }

    /**
     * Only a COMPLETED transaction can be voided. DepositService does the work
     * in one database transaction and PointsService appends the REVERSAL row;
     * nothing is edited or deleted and the balance may go below zero.
     */
    public function void(VoidTransactionRequest $request, Transaction $transaction): RedirectResponse
    {
        throw new \LogicException('Not implemented.');
    }
}
