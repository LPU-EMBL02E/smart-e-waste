<?php

namespace App\Modules\Rewards\Http\Controllers;

use App\Modules\Rewards\Models\Reward;
use App\Modules\Rewards\Services\PointsService;
use App\Modules\Rewards\Services\RewardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The student-facing rewards catalogue. API_Design.md §7.3.
 *
 *   GET /rewards              active rewards only
 *   GET /rewards/{reward}     one reward, with the redeem form
 *
 * The show page needs the user's balance so it can say whether they can afford
 * it, but affording it is still re-checked inside RedemptionService under a row
 * lock — the page is a hint, not the gate.
 *
 * An inactive reward is 404, the same as its absence from the catalogue
 * (owner decision for Track C, question 2).
 */
class RewardController
{
    public function __construct(
        private RewardService $rewards,
        private PointsService $points,
    ) {}

    public function index(): View
    {
        return view('rewards.rewards.index', ['rewards' => $this->rewards->catalogue()]);
    }

    public function show(Request $request, Reward $reward): View
    {
        abort_unless($reward->is_active, 404);

        return view('rewards.rewards.show', [
            'reward' => $reward,
            'balance' => $this->points->balanceFor($request->user()),
        ]);
    }
}
