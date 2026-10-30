<?php

namespace App\Modules\Rewards\Http\Controllers;

use App\Modules\Deposits\Services\DepositService;
use App\Modules\Rewards\Services\PointsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * GET /home — the student's landing page: points balance and recent deposits.
 * API_Design.md §7.3.
 *
 * The balance comes from PointsService::balanceFor(), which sums the ledger.
 * There is no balance column to read, and none should ever be added: the ledger
 * is the single source of truth (System_Plan.md §5.7 rule 7). A balance below
 * zero, left by a void after the points were spent, is shown as it is.
 *
 * The recent deposits are the first page of the same list as /transactions,
 * config('ewaste.home_recent_transactions') long.
 */
class HomeController
{
    public function __construct(
        private PointsService $points,
        private DepositService $deposits,
    ) {}

    public function __invoke(Request $request): View
    {
        $user = $request->user();

        return view('rewards.home', [
            'balance' => $this->points->balanceFor($user),
            'transactions' => $this->deposits->transactionsFor($user, config('ewaste.home_recent_transactions')),
        ]);
    }
}
