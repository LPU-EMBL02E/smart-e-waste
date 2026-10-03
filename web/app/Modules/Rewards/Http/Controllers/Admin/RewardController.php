<?php

namespace App\Modules\Rewards\Http\Controllers\Admin;

use App\Modules\Rewards\Http\Requests\Admin\StoreRewardRequest;
use App\Modules\Rewards\Models\Reward;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Admin reward management. API_Design.md §7.4.
 *
 * No delete route: a reward is switched off with is_active, because redemptions
 * reference it (P33).
 *
 * TODO: all method bodies.
 */
class RewardController
{
    public function index(): View
    {
        throw new \LogicException('Not implemented.');
    }

    public function create(): View
    {
        throw new \LogicException('Not implemented.');
    }

    public function store(StoreRewardRequest $request): RedirectResponse
    {
        throw new \LogicException('Not implemented.');
    }

    public function edit(Reward $reward): View
    {
        throw new \LogicException('Not implemented.');
    }

    public function update(StoreRewardRequest $request, Reward $reward): RedirectResponse
    {
        throw new \LogicException('Not implemented.');
    }
}
