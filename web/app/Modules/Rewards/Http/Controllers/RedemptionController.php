<?php

namespace App\Modules\Rewards\Http\Controllers;

use App\Modules\Rewards\Http\Requests\StoreRedemptionRequest;
use App\Modules\Rewards\Services\RedemptionService;
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
 *
 * TODO: all method bodies.
 */
class RedemptionController
{
    public function __construct(private RedemptionService $redemptions)
    {
    }

    public function store(StoreRedemptionRequest $request): RedirectResponse
    {
        throw new \LogicException('Not implemented.');
    }

    /** Scoped to $request->user(): a user sees only their own (§7.1). */
    public function index(Request $request): View
    {
        throw new \LogicException('Not implemented.');
    }
}
