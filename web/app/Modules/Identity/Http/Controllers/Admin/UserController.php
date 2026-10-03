<?php

namespace App\Modules\Identity\Http\Controllers\Admin;

use App\Modules\Identity\Http\Requests\Admin\ImportUsersRequest;
use App\Modules\Identity\Http\Requests\Admin\SetUserPasswordRequest;
use App\Modules\Identity\Http\Requests\Admin\StoreUserRequest;
use App\Modules\Identity\Http\Requests\Admin\UpdateUserRequest;
use App\Modules\Identity\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Admin user management. API_Design.md §7.4 and §7.6.
 *
 *   GET  /admin/users                        list, showing unactivated accounts
 *   GET  /admin/users/create                 form
 *   POST /admin/users                        create
 *   GET  /admin/users/{user}/edit            form
 *   PUT  /admin/users/{user}                 save
 *   POST /admin/users/import                 CSV import
 *   POST /admin/users/{user}/password-link   send or resend the set-password email
 *   PUT  /admin/users/{user}/password        admin sets a password (fallback)
 *
 * No delete route. A user is switched off with status = inactive, because other
 * tables reference them (P33).
 *
 * Creating a user inserts the users row with password NULL and its
 * user_qr_credentials row in ONE database transaction, so a student can never
 * exist without a QR token.
 *
 * The set-password email is sent during the request. If the send fails the user
 * is still created and the admin is told the email was not sent — the mail
 * failure must not fail the action (System_Plan.md §7.2).
 *
 * TODO: all method bodies.
 */
class UserController
{
    /** An account is "not activated" while users.password is NULL. */
    public function index(): View
    {
        // TODO
        throw new \LogicException('Not implemented.');
    }

    public function create(): View
    {
        // TODO
        throw new \LogicException('Not implemented.');
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        // TODO: user + qr credential in one transaction, then send the link
        throw new \LogicException('Not implemented.');
    }

    public function edit(User $user): View
    {
        // TODO
        throw new \LogicException('Not implemented.');
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        // TODO
        throw new \LogicException('Not implemented.');
    }

    public function import(ImportUsersRequest $request): RedirectResponse
    {
        // TODO: validate every row first; import all or none; send no email
        throw new \LogicException('Not implemented.');
    }

    /** Send or resend the set-password email through Laravel's password broker. */
    public function sendPasswordLink(User $user): RedirectResponse
    {
        // TODO
        throw new \LogicException('Not implemented.');
    }

    public function setPassword(SetUserPasswordRequest $request, User $user): RedirectResponse
    {
        // TODO
        throw new \LogicException('Not implemented.');
    }
}
