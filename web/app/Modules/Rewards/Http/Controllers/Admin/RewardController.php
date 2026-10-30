<?php

namespace App\Modules\Rewards\Http\Controllers\Admin;

use App\Modules\Rewards\Http\Requests\Admin\StoreRewardRequest;
use App\Modules\Rewards\Models\Reward;
use App\Modules\Rewards\Services\RewardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Admin reward management. API_Design.md §7.4 and §7.6 ("Create or edit a
 * reward").
 *
 * No delete route: a reward is switched off with is_active, because redemptions
 * reference it (P33).
 */
class RewardController
{
    public function __construct(private RewardService $rewards) {}

    public function index(): View
    {
        return view('rewards.admin.rewards.index', ['rewards' => $this->rewards->list()]);
    }

    public function create(): View
    {
        return view('rewards.admin.rewards.create', ['reward' => new Reward]);
    }

    public function store(StoreRewardRequest $request): RedirectResponse
    {
        $this->rewards->create($request->validated());

        return redirect()->route('admin.rewards.index')->with('status', 'Reward created.');
    }

    public function edit(Reward $reward): View
    {
        return view('rewards.admin.rewards.edit', ['reward' => $reward]);
    }

    public function update(StoreRewardRequest $request, Reward $reward): RedirectResponse
    {
        $this->rewards->update($reward, $request->validated());

        return redirect()->route('admin.rewards.index')->with('status', 'Reward saved.');
    }
}
