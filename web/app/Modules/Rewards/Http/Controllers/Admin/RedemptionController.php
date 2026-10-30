<?php

namespace App\Modules\Rewards\Http\Controllers\Admin;

use App\Modules\Rewards\Http\Requests\Admin\CancelRedemptionRequest;
use App\Modules\Rewards\Models\Redemption;
use App\Modules\Rewards\Services\RedemptionService;
use App\Modules\Rewards\Support\RedemptionRefused;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Admin redemption fulfilment. API_Design.md §7.4.
 *
 *   GET  /admin/redemptions                        list
 *   POST /admin/redemptions/{redemption}/fulfil    mark fulfilled
 *   POST /admin/redemptions/{redemption}/cancel    cancel a pending one
 *
 * Both actions are only possible while the redemption is PENDING. Cancelling
 * returns the stock and gives the points back as a REVERSAL row; a FULFILLED
 * redemption cannot be cancelled, because the student already has the item
 * (System_Plan.md §6.1).
 *
 * A refusal — the redemption is no longer PENDING — redirects back with the
 * message and changes nothing.
 */
class RedemptionController
{
    public function __construct(private RedemptionService $redemptions) {}

    public function index(): View
    {
        return view('rewards.admin.redemptions.index', ['redemptions' => $this->redemptions->list()]);
    }

    public function fulfil(Redemption $redemption): RedirectResponse
    {
        try {
            $this->redemptions->fulfil($redemption);
        } catch (RedemptionRefused $refused) {
            return back()->withErrors(['redemption' => $refused->getMessage()]);
        }

        return back()->with('status', "{$redemption->redemption_code} marked fulfilled.");
    }

    public function cancel(CancelRedemptionRequest $request, Redemption $redemption): RedirectResponse
    {
        try {
            $this->redemptions->cancel($redemption, $request->validated('cancel_reason'));
        } catch (RedemptionRefused $refused) {
            return back()->withErrors(['redemption' => $refused->getMessage()]);
        }

        return back()->with('status', "{$redemption->redemption_code} cancelled. The points and stock are back.");
    }
}
