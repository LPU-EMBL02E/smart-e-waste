<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Modules\Notifications\Models\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * GET /notifications and POST /notifications/{notification}/read.
 * API_Design.md §7.3.
 *
 * One route for both roles: admins read their bin-full alerts here, the same
 * place students read theirs (§7.4).
 *
 * A user may only see their own, so both methods are scoped to
 * $request->user() — marking another user's notification read must 404 or 403,
 * never succeed.
 *
 * TODO: all method bodies.
 */
class NotificationController
{
    public function index(Request $request): View
    {
        throw new \LogicException('Not implemented.');
    }

    /** Sets read_at. */
    public function markRead(Request $request, Notification $notification): RedirectResponse
    {
        throw new \LogicException('Not implemented.');
    }
}
