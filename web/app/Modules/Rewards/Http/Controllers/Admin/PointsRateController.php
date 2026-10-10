<?php

namespace App\Modules\Rewards\Http\Controllers\Admin;

use App\Modules\Rewards\Http\Requests\Admin\StoreRewardRuleRequest;
use App\Modules\Rewards\Services\RewardRuleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * GET and POST /admin/points-rate. API_Design.md §7.4.
 *
 * Shows the rule in effect and every past rule, and saves a new one. There is
 * no edit: saving closes the current rule and inserts a new one, so past
 * transactions keep the rule they were priced with
 * (System_Plan.md §5.7 rule 6).
 *
 * The bin refuses every deposit with NO_REWARD_RULE until the first rule exists,
 * so this page is one of the two things an admin must do before first use
 * (the other is registering the bin).
 *
 * TODO: all method bodies.
 */
class PointsRateController
{
    public function __construct(private RewardRuleService $rules) {}

    public function show(): View
    {
        throw new \LogicException('Not implemented.');
    }

    public function store(StoreRewardRuleRequest $request): RedirectResponse
    {
        throw new \LogicException('Not implemented.');
    }
}
