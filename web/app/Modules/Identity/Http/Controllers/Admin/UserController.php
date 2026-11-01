<?php

namespace App\Modules\Identity\Http\Controllers\Admin;

use App\Modules\Identity\Http\Requests\Admin\ImportUsersRequest;
use App\Modules\Identity\Http\Requests\Admin\SetUserPasswordRequest;
use App\Modules\Identity\Http\Requests\Admin\StoreUserRequest;
use App\Modules\Identity\Http\Requests\Admin\UpdateUserRequest;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\AccountService;
use App\Modules\Identity\Services\OrganizationService;
use App\Modules\Identity\Support\AccountChangeRefused;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
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
 */
class UserController
{
    public function __construct(
        private AccountService $accounts,
        private OrganizationService $organizations,
    ) {}

    /** An account is "not activated" while users.password is NULL. */
    public function index(): View
    {
        return view('identity.admin.users.index', ['users' => $this->accounts->list()]);
    }

    public function create(): View
    {
        return view('identity.admin.users.create', [
            'user' => new User(['role' => User::ROLE_STUDENT, 'status' => User::STATUS_ACTIVE]),
            'organizations' => $this->organizations->activeOptions(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = $this->accounts->createByAdmin($request->validated());

        $status = $this->accounts->sendPasswordLinkTo($user)
            ? "{$user->fullName()} was created. A set-password email was sent to {$user->email}."
            : "{$user->fullName()} was created, but the set-password email was not sent. An inactive account gets no email; otherwise resend it from the list, or set a password for them.";

        return redirect()->route('admin.users.index')->with('status', $status);
    }

    public function edit(User $user): View
    {
        return view('identity.admin.users.edit', [
            'user' => $user,
            'organizations' => $this->organizations->activeOptions($user->organization_id),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        try {
            $this->accounts->update($user, $request->validated());
        } catch (AccountChangeRefused $refused) {
            return back()->withErrors(['status' => $refused->getMessage()])->withInput();
        }

        return redirect()->route('admin.users.index')->with('status', "{$user->fullName()} was saved.");
    }

    public function import(ImportUsersRequest $request): RedirectResponse
    {
        $result = $this->accounts->importFile($request->file('file')->getRealPath());

        if ($result['errors'] !== []) {
            return back()->with('import_errors', $result['errors']);
        }

        return back()->with(
            'status',
            "Imported {$result['imported']} ".Str::plural('student', $result['imported']).". They can't sign in until they set a password: send each one a set-password email from the list.",
        );
    }

    /** Send or resend the set-password email. */
    public function sendPasswordLink(User $user): RedirectResponse
    {
        $status = $this->accounts->sendPasswordLinkTo($user)
            ? "Set-password email sent to {$user->email}."
            : 'The email could not be sent. An inactive account gets no email; otherwise set a password on the user\'s edit page instead.';

        return back()->with('status', $status);
    }

    public function setPassword(SetUserPasswordRequest $request, User $user): RedirectResponse
    {
        $this->accounts->setPassword($user, $request->validated('password'));

        return back()->with('status', "A new password was set for {$user->fullName()}.");
    }
}
