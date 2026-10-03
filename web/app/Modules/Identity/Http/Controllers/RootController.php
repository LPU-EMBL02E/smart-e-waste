<?php

namespace App\Modules\Identity\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * GET / — send a signed-in user to the right place for their role:
 * /admin for an admin, /home for a student. API_Design.md §7.3.
 *
 * There are only two roles, so there is no third case to handle.
 */
class RootController
{
    public function __invoke(Request $request): RedirectResponse
    {
        return $request->user()->isAdmin()
            ? redirect()->route('admin.dashboard')
            : redirect()->route('home');
    }
}
