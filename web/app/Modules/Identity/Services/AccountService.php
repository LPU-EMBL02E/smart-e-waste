<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserQrCredential;
use App\Modules\Identity\Support\AccountChangeRefused;
use App\Modules\Identity\Support\LoginRefused;
use App\Modules\Identity\Support\QrToken;
use App\Modules\Identity\Support\UserImportFile;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Account rules and writes. System_Plan.md §4: Identity is the only writer of
 * `organizations`, `users` and `user_qr_credentials`, so other modules change
 * them only through this service. web/docs/TRACK-A-ACCOUNTS.md steps A2 and A3.
 *
 * A user and its QR credential are always created in one transaction. Mail goes
 * out after the commit, never inside a transaction, so a failed send never undoes
 * a write (System_Plan.md §7.2).
 *
 * Setting a password hashes it, which is slow, so that happens before each
 * transaction opens rather than inside it (AGENTS.md §5.1).
 */
class AccountService
{
    /** Rows per insert in a CSV import, and values per lookup while checking one. */
    private const IMPORT_BATCH_SIZE = 500;

    /** The same limits as the admin's user form. API_Design.md §7.6. */
    private const IMPORT_ROW_RULES = [
        'student_number' => ['nullable', 'string', 'max:50'],
        'first_name' => ['required', 'string', 'max:100'],
        'last_name' => ['required', 'string', 'max:100'],
        'email' => ['required', 'email', 'max:255'],
        'organization_code' => ['required', 'string'],
    ];

    /**
     * Sign in through Laravel's guard. API_Design.md §7.2, P6, P29.
     *
     * The guard already fails an account with no password, and asking for an
     * active status makes an inactive account fail the same way. Only failures
     * count towards the limit, so someone signing in normally is never slowed.
     *
     * @throws LoginRefused
     */
    public function signIn(string $email, string $password, string $throttleKey): User
    {
        if (RateLimiter::tooManyAttempts($throttleKey, config('ewaste.login.max_attempts'))) {
            throw LoginRefused::tooManyAttempts(RateLimiter::availableIn($throttleKey));
        }

        $signedIn = Auth::attempt([
            'email' => $email,
            'password' => $password,
            'status' => User::STATUS_ACTIVE,
        ]);

        if (! $signedIn) {
            RateLimiter::hit($throttleKey, config('ewaste.login.decay_seconds'));

            throw LoginRefused::invalidCredentials();
        }

        RateLimiter::clear($throttleKey);

        /** @var User $user */
        $user = Auth::user();
        $user->forceFill(['last_login_at' => now()])->save();

        return $user;
    }

    /**
     * Send a link to set a password. The broker finds no account for an unknown
     * or inactive email, so neither gets a link (API_Design.md P30,
     * web/docs/ACCOUNT-MANAGEMENT.md).
     *
     * The reply must not depend on the account, so a failed send is only logged
     * and the caller can't tell it happened. The broker also limits an account to
     * one link a minute, which is what a public form needs.
     */
    public function sendPasswordLink(string $email): void
    {
        $this->attemptToSend(
            fn () => Password::sendResetLink(['email' => $email, 'status' => User::STATUS_ACTIVE]),
        );
    }

    /**
     * An admin sends the set-password email. API_Design.md §7.4, §7.6.
     *
     * Unlike the public form, this goes out at once, with no limit of one a
     * minute, and the admin is told if it didn't. The new link replaces any
     * earlier one. An inactive account can't use a link, so it gets none
     * (web/docs/ACCOUNT-MANAGEMENT.md).
     *
     * @return bool whether the email was sent
     */
    public function sendPasswordLinkTo(User $user): bool
    {
        if (! $user->isActive()) {
            return false;
        }

        return $this->attemptToSend(
            fn () => $user->sendPasswordResetNotification(Password::createToken($user)),
        );
    }

    /**
     * Tell the app a student has registered, once register() has committed.
     * Laravel's own listener sends the confirmation email (API_Design.md §7.6).
     *
     * @return bool whether the email was sent; the account stands either way
     */
    public function announceRegistration(User $user): bool
    {
        return $this->attemptToSend(fn () => event(new Registered($user)));
    }

    /**
     * Send the confirmation link again. API_Design.md §7.2.
     *
     * @return bool whether the email was sent
     */
    public function sendConfirmationEmail(User $user): bool
    {
        return $this->attemptToSend(fn () => $user->sendEmailVerificationNotification());
    }

    /**
     * Set a password from an emailed link. Refuses a wrong or expired token, an
     * unknown email and an inactive account alike, so a refusal reveals nothing
     * about the account (API_Design.md §7.2, web/docs/ACCOUNT-MANAGEMENT.md).
     *
     * @return bool whether the password was set
     */
    public function resetPassword(string $email, string $token, string $password): bool
    {
        $status = Password::reset(
            ['email' => $email, 'token' => $token, 'password' => $password, 'status' => User::STATUS_ACTIVE],
            fn (User $user, string $newPassword) => $this->setPassword($user, $newPassword),
        );

        return $status === Password::PASSWORD_RESET;
    }

    /**
     * Self-registration: always an active, unconfirmed student, whatever the
     * input says. API_Design.md §7.6 ("Register"). The caller then calls
     * announceRegistration(), so the email goes out after the commit.
     */
    public function register(array $data): User
    {
        $user = new User([
            ...$data,
            'role' => User::ROLE_STUDENT,
            'status' => User::STATUS_ACTIVE,
        ]);
        $user->forceFill(['password' => $data['password']]);

        return $this->createWithQrCredential($user);
    }

    /**
     * An account an admin enters: confirmed (P47), with no password until the
     * student sets one from the emailed link. API_Design.md §7.6.
     */
    public function createByAdmin(array $data): User
    {
        $user = new User($data);
        $user->forceFill(['email_verified_at' => now()]);

        return $this->createWithQrCredential($user);
    }

    /**
     * Edit details, role and status. The email stays confirmed when it changes,
     * because an admin entered it (P47).
     *
     * A change of email or role, or disabling the account, ends its sessions and
     * deletes its password links (web/docs/ACCOUNT-MANAGEMENT.md).
     *
     * @throws AccountChangeRefused if this would leave no usable admin
     */
    public function update(User $user, array $data): User
    {
        $user->fill($data);

        // Admins have no organization (System_Plan.md §5.6). The form only
        // requires the field to be empty, so a student made admin would keep
        // their old one without this.
        if ($user->isAdmin()) {
            $user->organization_id = null;
        }

        DB::transaction(function () use ($user) {
            $this->refuseRemovingLastAdmin($user);

            $endsAccess = $user->isDirty(['email', 'role'])
                || ($user->isDirty('status') && ! $user->isActive());

            // Password links are keyed by email, so this needs the old address.
            $previousEmail = $user->getOriginal('email');

            $user->save();

            if ($endsAccess) {
                $this->endSessions($user);
                $this->deletePasswordLinks($previousEmail);
            }
        });

        return $user;
    }

    /**
     * The admin's fallback when email can't be sent. API_Design.md §7.6.
     *
     * Ends every session of the account, including the caller's if an admin sets
     * their own password (web/docs/ACCOUNT-MANAGEMENT.md, "Sessions after account
     * changes").
     */
    public function setPassword(User $user, string $password): void
    {
        $user->forceFill(['password' => $password]);

        DB::transaction(function () use ($user) {
            $user->save();

            $this->endSessions($user);
            $this->deletePasswordLinks($user->email);
        });
    }

    /**
     * Overwrites the token, so the old one stops matching at once.
     * Database_Schema.md §3.
     */
    public function regenerateQr(User $user): UserQrCredential
    {
        $credential = $user->qrCredential()->firstOrFail();
        $credential->update(['token' => QrToken::generate()]);

        return $credential;
    }

    /**
     * What the profile page shows, loaded here so the view runs no query.
     * API_Design.md §7.3.
     */
    public function loadProfile(User $user): User
    {
        return $user->load(['organization', 'qrCredential']);
    }

    /**
     * The admin user list, newest first, so imported accounts are at the top to
     * send their emails. API_Design.md §7.4. Sorting on the primary key needs no
     * new index on `users`.
     *
     * @return LengthAwarePaginator<int, User>
     */
    public function list(): LengthAwarePaginator
    {
        return User::with('organization:id,code')->latest('id')->paginate();
    }

    /**
     * Import an uploaded CSV file, all rows or none. API_Design.md §7.6, P36.
     * Errors are keyed by row number.
     *
     * @return array{imported: int, errors: array<int, list<string>>}
     */
    public function importFile(string $path): array
    {
        ['rows' => $rows, 'errors' => $errors] = UserImportFile::read($path, array_keys(self::IMPORT_ROW_RULES));

        if ($errors === []) {
            $errors = $this->import($rows);
        }

        return ['imported' => $errors === [] ? count($rows) : 0, 'errors' => $errors];
    }

    /**
     * CSV import, all rows or none. API_Design.md §7.6, P36, P47.
     *
     * Every row is checked first, with one lookup per batch rather than one per
     * row, then every user and QR credential goes in as a batch. The Pi has to
     * finish a large file inside one request. Sends no email.
     *
     * @param  array<int, array<string, mixed>>  $rows  keyed by CSV line number
     * @return array<int, list<string>> error messages by line number; empty means
     *                                  every row was imported
     */
    public function import(array $rows): array
    {
        $rows = array_map($this->normalizeImportRow(...), $rows);

        $organizationIds = Organization::active()
            ->whereIn('code', array_unique(array_column($rows, 'organization_code')))
            ->pluck('id', 'code')
            ->all();

        $errors = $this->importErrors($rows, $organizationIds);

        if ($errors !== []) {
            return $errors;
        }

        DB::transaction(function () use ($rows, $organizationIds) {
            $now = now();

            foreach (array_chunk($rows, self::IMPORT_BATCH_SIZE) as $batch) {
                User::insert(array_map(fn (array $row): array => [
                    'organization_id' => $organizationIds[$row['organization_code']],
                    'role' => User::ROLE_STUDENT,
                    'student_number' => $row['student_number'],
                    'email' => $row['email'],
                    'email_verified_at' => $now,
                    'first_name' => $row['first_name'],
                    'last_name' => $row['last_name'],
                    'status' => User::STATUS_ACTIVE,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $batch));

                $userIds = User::whereIn('email', array_column($batch, 'email'))->pluck('id');

                UserQrCredential::insert($userIds->map(fn (int $userId): array => [
                    'user_id' => $userId,
                    'token' => QrToken::generate(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
            }
        });

        return [];
    }

    /**
     * Record that a QR token was just scanned at a bin. API_Design.md §5.4, P6.
     *
     * Called by DepositService inside the transaction that opens the session,
     * so the time is written only when the session is. It leaves updated_at alone,
     * because that is the time the token was last regenerated (Database_Schema.md
     * §3), which the profile page shows.
     */
    public function markQrCredentialUsed(UserQrCredential $credential): void
    {
        UserQrCredential::withoutTimestamps(
            fn () => $credential->forceFill(['last_used_at' => now()])->save(),
        );
    }

    /**
     * A failed send is logged and never fails the action (System_Plan.md §7.2).
     */
    private function attemptToSend(callable $send): bool
    {
        try {
            $send();

            return true;
        } catch (Throwable $failure) {
            report($failure);

            return false;
        }
    }

    private function createWithQrCredential(User $user): User
    {
        DB::transaction(function () use ($user) {
            $user->save();
            $user->qrCredential()->create(['token' => QrToken::generate()]);
        });

        return $user;
    }

    /**
     * Another active admin with a password must remain, including when an admin
     * edits their own account (web/docs/ACCOUNT-MANAGEMENT.md).
     *
     * The lock is the first read, and the count comes from it rather than from
     * the loaded model. Two admins disabling each other at the same moment
     * therefore run one after the other, and the second sees the first's change.
     */
    private function refuseRemovingLastAdmin(User $user): void
    {
        $wasAdmin = $user->getOriginal('role') === User::ROLE_ADMIN;
        $staysUsableAdmin = $user->isAdmin() && $user->isActive();

        if (! $wasAdmin || $staysUsableAdmin) {
            return;
        }

        $usableAdminIds = User::where('role', User::ROLE_ADMIN)
            ->where('status', User::STATUS_ACTIVE)
            ->whereNotNull('password')
            ->lockForUpdate()
            ->pluck('id');

        if ($usableAdminIds->count() === 1 && $usableAdminIds->contains($user->getKey())) {
            throw AccountChangeRefused::lastAdmin();
        }
    }

    /**
     * Signs the user out on their next request.
     *
     * This only works with SESSION_DRIVER=database, the current setting. Moving
     * to file or Redis (System_Plan.md §8.1) needs this replaced first, or a
     * disabled user would stay signed in.
     */
    private function endSessions(User $user): void
    {
        DB::table(config('session.table'))->where('user_id', $user->getKey())->delete();
    }

    private function deletePasswordLinks(string $email): void
    {
        DB::table(config('auth.passwords.users.table'))->where('email', $email)->delete();
    }

    /**
     * Only the five columns, trimmed. A blank student number is NULL, so that it
     * can't collide with another blank on the unique column.
     *
     * @return array<string, string|null>
     */
    private function normalizeImportRow(array $row): array
    {
        $normalized = [];

        foreach (array_keys(self::IMPORT_ROW_RULES) as $column) {
            $normalized[$column] = trim((string) ($row[$column] ?? ''));
        }

        if ($normalized['student_number'] === '') {
            $normalized['student_number'] = null;
        }

        return $normalized;
    }

    /**
     * @param  array<int, array<string, string|null>>  $rows
     * @param  array<string, int>  $organizationIds  active organizations, by code
     * @return array<int, list<string>>
     */
    private function importErrors(array $rows, array $organizationIds): array
    {
        $takenEmails = $this->existingUserValues('email', array_column($rows, 'email'));
        $takenNumbers = $this->existingUserValues(
            'student_number',
            array_filter(array_column($rows, 'student_number'), fn (?string $number): bool => $number !== null),
        );

        $errors = [];

        foreach ($rows as $line => $row) {
            $messages = Validator::make($row, self::IMPORT_ROW_RULES)->errors()->all();

            // Each value joins the taken list as it is read, so a duplicate inside
            // the file fails the same check as one already in the database.
            $email = mb_strtolower($row['email']);

            if ($email !== '') {
                if (isset($takenEmails[$email])) {
                    $messages[] = 'This email is already used by an account or an earlier row.';
                }

                $takenEmails[$email] = true;
            }

            if ($row['student_number'] !== null) {
                $number = mb_strtolower($row['student_number']);

                if (isset($takenNumbers[$number])) {
                    $messages[] = 'This student number is already used by an account or an earlier row.';
                }

                $takenNumbers[$number] = true;
            }

            if ($row['organization_code'] !== '' && ! isset($organizationIds[$row['organization_code']])) {
                $messages[] = 'No active organization has this code.';
            }

            if ($messages !== []) {
                $errors[$line] = $messages;
            }
        }

        return $errors;
    }

    /**
     * Which of these values users.$column already holds, lower-cased because the
     * database's unique check ignores case.
     *
     * @param  list<string>  $values
     * @return array<string, true>
     */
    private function existingUserValues(string $column, array $values): array
    {
        $existing = [];

        foreach (array_chunk($values, self::IMPORT_BATCH_SIZE) as $batch) {
            foreach (User::whereIn($column, $batch)->pluck($column) as $value) {
                $existing[mb_strtolower($value)] = true;
            }
        }

        return $existing;
    }
}
