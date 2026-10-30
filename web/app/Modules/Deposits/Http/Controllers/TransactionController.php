<?php

namespace App\Modules\Deposits\Http\Controllers;

use App\Modules\Deposits\Services\DepositService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * GET /transactions — a user's own deposit history. API_Design.md §7.3.
 *
 * A user may only ever see their own (§7.1), so the query is scoped to
 * $request->user() and never takes a user id from the request.
 *
 * Each row shows the session's weight and the points awarded, and marks voided
 * transactions, so a student can see why a balance changed.
 */
class TransactionController
{
    public function __construct(private DepositService $deposits) {}

    public function index(Request $request): View
    {
        return view('deposits.transactions.index', [
            'transactions' => $this->deposits->transactionsFor($request->user()),
        ]);
    }
}
