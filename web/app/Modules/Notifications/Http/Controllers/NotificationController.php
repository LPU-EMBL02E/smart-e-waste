<?php

namespace App\Modules\Notifications\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * GET /notifications and POST /notifications/{notification}/read.
 * API_Design.md §7.3.
 *
 * One route for both roles: admins read their bin-full alerts here, the same
 * place students read theirs (§7.4). An admin sees it in the admin shell,
 * unless they came from the student shell ("Student view"): its link adds
 * ?portal=student so the admin stays in the shell they were using. A student
 * always gets the student shell, whatever the query says.
 *
 * A user may only see their own, so both methods are scoped to
 * $request->user(). markRead looks the id up through the user's own
 * notifications, so another user's is simply not found (404) — it is never
 * route-bound, which would load it first and check after.
 */
class NotificationController
{
    /** Newest first, paginated. The page links keep ?portal so the shell stays put. */
    public function index(Request $request): View
    {
        $user = $request->user();
        $inAdminShell = $user->isAdmin() && $request->query('portal') !== 'student';

        return view('notifications.index', [
            'layout' => $inAdminShell ? 'layouts.admin' : 'layouts.app',
            'notifications' => $user->notifications()->latest('id')->paginate()->withQueryString(),
        ]);
    }

    /** Sets read_at, once. */
    public function markRead(Request $request, string $notification): RedirectResponse
    {
        $request->user()->notifications()->findOrFail($notification)->markAsRead();

        return back();
    }
}
