<?php

namespace App\Modules\Rewards\Http\Controllers;

use App\Modules\Rewards\Services\PointsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * GET /home — the student's landing page: points balance and recent deposits.
 * API_Design.md §7.3.
 *
 * The balance comes from PointsService::balanceFor(), which sums the ledger.
 * There is no balance column to read, and none should ever be added: the ledger
 * is the single source of truth (System_Plan.md §5.7 rule 7).
 *
 * TODO: method body.
 */
class HomeController
{
    public function __construct(private PointsService $points) {}

    public function __invoke(Request $request): View
    {
        throw new \LogicException('Not implemented.');
    }
}
