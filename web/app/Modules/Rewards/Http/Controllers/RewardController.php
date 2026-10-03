<?php

namespace App\Modules\Rewards\Http\Controllers;

use App\Modules\Rewards\Models\Reward;
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
 * TODO: all method bodies.
 */
class RewardController
{
    public function index(): View
    {
        throw new \LogicException('Not implemented.');
    }

    public function show(Request $request, Reward $reward): View
    {
        throw new \LogicException('Not implemented.');
    }
}
