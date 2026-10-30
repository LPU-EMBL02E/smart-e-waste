<?php

namespace App\Modules\Rewards\Http\Controllers;

use App\Modules\Rewards\Http\Requests\StoreRedemptionRequest;
use App\Modules\Rewards\Services\RedemptionService;
use App\Modules\Rewards\Support\RedemptionRefused;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Student redemptions. API_Design.md §7.3.
 *
 *   POST /redemptions     redeem a reward
 *   GET  /redemptions     own redemptions
 *
 * Redeeming needs the website login. A QR scan at the bin identifies a student
 * for one deposit and can never spend points, which is why the bin asks for no
 * password or PIN (System_Plan.md §5.5).
 *
 * A refusal — not enough points, or out of stock — redirects back with a
 * message and changes nothing.
 */
class RedemptionController
{
    public function __construct(private RedemptionService $redemptions) {}

    public function store(StoreRedemptionRequest $request): RedirectResponse
    {
        try {
            $redemption = $this->redemptions->redeem(
                $request->user(),
                $request->reward(),
                (int) $request->validated('quantity'),
            );
        } catch (RedemptionRefused $refused) {
            return back()->withInput()->withErrors(['redemption' => $refused->getMessage()]);
        }

        return redirect()->route('redemptions.index')->with(
            'status',
            "Redeemed {$redemption->reward->name}. Your code is {$redemption->redemption_code}.",
        );
    }

    /** Scoped to $request->user(): a user sees only their own (§7.1). */
    public function index(Request $request): View
    {
        return view('rewards.redemptions.index', [
            'redemptions' => $this->redemptions->listFor($request->user()),
        ]);
    }
}
