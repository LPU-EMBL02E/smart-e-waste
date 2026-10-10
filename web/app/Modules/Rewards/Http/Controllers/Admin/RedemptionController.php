<?php

namespace App\Modules\Rewards\Http\Controllers\Admin;

use App\Modules\Rewards\Models\Redemption;
use App\Modules\Rewards\Services\RedemptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
 * TODO: all method bodies.
 */
class RedemptionController
{
    public function __construct(private RedemptionService $redemptions) {}

    public function index(): View
    {
        throw new \LogicException('Not implemented.');
    }

    public function fulfil(Redemption $redemption): RedirectResponse
    {
        throw new \LogicException('Not implemented.');
    }

    public function cancel(Request $request, Redemption $redemption): RedirectResponse
    {
        throw new \LogicException('Not implemented.');
    }
}
