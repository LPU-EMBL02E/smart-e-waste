# Track A — Accounts

Build spec for the Identity module: registration, email confirmation, login,
passwords, and the admin user and organization tools. It runs in parallel with
[Track B](TRACK-B-DEPOSIT-PATH.md).

The rules come from `docs/`, which wins over this sheet if the two ever disagree:

- `System_Plan.md` §4 (accounts, module boundaries) and §7.2 (email)
- `API_Design.md` §7.2–§7.4 (routes), §7.6 (form rules), P29–P31, P33, P35–P36,
  P41, P46–P48
- `Database_Schema.md` §1–§3

Account policies that `docs/` leaves open are taken from
[ACCOUNT-MANAGEMENT.md](ACCOUNT-MANAGEMENT.md), except its "no public
self-registration": students now register themselves and confirm their email,
with no admin approval (`System_Plan.md` §4).

**Logic first, interface later.** Every step ends with working logic and feature
tests. Views are bare Blade (see "Views" below); the design pass from
[DESIGN.md](DESIGN.md) comes after the logic works.

---

## Module boundaries

Everything in this track lives in `app/Modules/Identity/`. It owns
`organizations`, `users` and `user_qr_credentials`, and is the only code that
writes them (`System_Plan.md` §4, boundary rule 1).

| Folder | Holds |
|---|---|
| `Services/` | `AccountService`, `OrganizationService` — every rule and every write |
| `Http/Controllers/` | Shape the request, call one service method, return a view or redirect. No queries, no rules |
| `Http/Requests/` | All validation, as FormRequests |
| `Http/Middleware/` | `EnsureUserIsAdmin` (exists) |
| `Support/` | `QrToken` (exists) |
| `Models/` | `User`, `Organization`, `UserQrCredential` (exist) |

Rules for this track:

- **Controllers are thin.** A controller method validates through its
  FormRequest, calls a service, and returns. A rule written in a controller is a
  bug.
- **One database transaction per write that touches more than one row**, opened
  in the service. A user and its QR credential are always created together.
- **Mail is sent after the commit, and a failed send never undoes the write.**
  Catch the failure and say the email was not sent (`System_Plan.md` §7.2).
- **Identity calls no other module.** `/home` (Rewards) and `/admin`
  (Reporting) are other modules' pages; this track only gives them account
  information (step A8).
- No new top-level folder, module, table or dependency. The QR image package is
  an open question (`API_Design.md` §10 q8), so the token is shown as text until
  it is decided.

---

## Step A0 — Shared foundation (do first, one writer)

Track B starts once this is merged.

1. **Bare layouts:** `resources/views/layouts/{guest,app,admin}.blade.php`. Each
   has a `<title>`, a nav with links to the routes that exist, the flash message
   (`session('status')`), the validation errors, and `@yield('content')`. No
   styling work.
2. **Factories** in `database/factories/Identity/`, namespace
   `Database\Factories\Identity`, each model pointing at its factory through
   `newFactory()`:
   - `OrganizationFactory` — active by default; `inactive()` state.
   - `UserFactory` — an active, confirmed student with a password and an
     organization by default. States: `admin()`, `inactive()`,
     `unconfirmed()` (`email_verified_at` NULL), `unactivated()` (`password`
     NULL).
   - `UserQrCredentialFactory` — token from `QrToken::generate()`. The user
     factory creates one for every user, as the app does.
3. **Test layout:** feature tests in `tests/Feature/<Module>/`, using
   `RefreshDatabase` on the in-memory SQLite database `phpunit.xml` already
   sets. Names follow `test_it_<does_something>`, one behaviour per test, and the
   class docblock cites the doc section.

Tests: `test_it_builds_each_factory_state` for the user factory.

---

## Step A1 — Email confirmation column and the `verified` gate

1. **New migration** `add_email_verified_at_to_users_table`: `email_verified_at`
   TIMESTAMP NULL. Do not edit the merged users migration.
2. `User` implements `MustVerifyEmail`; cast `email_verified_at` to `datetime`.
   Update the class docblock, which still says there is no self-registration.
3. **Routes:** every signed-in and admin group in every module's
   `routes/web.php` changes from `auth` to `auth` + `verified`
   (`API_Design.md` §7.3–§7.4). This touches the route files of all six
   modules, so one writer does it in one commit, announced to Track B.

Tests:
- `test_it_redirects_an_unconfirmed_user_to_the_confirmation_page`
- `test_it_lets_a_confirmed_user_reach_home`

---

## Step A2 — `AccountService` and `OrganizationService`

New: `app/Modules/Identity/Services/AccountService.php` and
`OrganizationService.php`. Constructor property promotion for dependencies;
every parameter and return typed.

**`AccountService`**

| Method | Does |
|---|---|
| `register(array $data): User` | Self-registration. In one transaction: insert the user (`role` student, `status` active, `email_verified_at` NULL, password hashed) and its QR credential. Returns the user; the caller fires `Registered` after the commit |
| `createByAdmin(array $data): User` | In one transaction: insert the user with `password` NULL and `email_verified_at` = now (P47), and its QR credential |
| `update(User $user, array $data, User $actor): User` | Edit details, role and status. Refuses to disable or demote the last active, activated admin (see below). On a change of email, role or status to inactive: end the user's sessions and delete their password links |
| `setPassword(User $user, string $password): void` | The admin fallback. Sets the password, ends the user's other sessions, deletes their password links |
| `regenerateQr(User $user): UserQrCredential` | Overwrites the token (`Database_Schema.md` §3) |
| `import(array $rows): array` | CSV import, all rows or none (P36). Validates every row first and returns the row errors; with no errors, inserts all users and QR credentials in one transaction. Accounts count as confirmed (P47). Sends no email |

**`OrganizationService`**: `create(array $data): Organization` and
`update(Organization $organization, array $data): Organization`. An inactive
organization keeps its students but takes no new ones; that check lives in the
FormRequests (`exists` on an organization whose `is_active` is true) for
registration, admin create and import.

**The last-admin rule** ([ACCOUNT-MANAGEMENT.md](ACCOUNT-MANAGEMENT.md)): in one
transaction, lock the active, activated admin rows (`lockForUpdate()`) as the
first read, count them, and refuse the change if it would leave none. The lock is
what stops two admins disabling each other at the same moment.

**Ending sessions and links:** `SESSION_DRIVER` is `database`, so ending a user's
sessions is deleting their rows from `sessions` through the query builder; they
are signed out on their next request. Password links are the user's row in
`password_reset_tokens`. Both are private methods of `AccountService`.

Tests (`tests/Feature/Identity/AccountServiceTest.php`):
- `test_it_registers_a_student_with_a_qr_credential`
- `test_it_creates_an_admin_made_account_as_confirmed_and_unactivated`
- `test_it_refuses_to_disable_the_last_active_admin`
- `test_it_refuses_to_demote_the_last_active_admin`
- `test_it_ends_sessions_and_links_when_an_account_is_disabled`
- `test_it_ends_sessions_when_the_email_changes`
- `test_it_regenerates_the_qr_token`
- `test_it_imports_all_rows_or_none`
- `test_it_refuses_a_new_student_in_an_inactive_organization`

---

## Step A3 — Login, logout and password links

Existing controllers in `Http/Controllers/Auth/`; fill in their bodies.

| Route | Behaviour |
|---|---|
| `POST /login` | Fails with one generic message for a wrong password, a NULL password and an inactive user (P29). 5 attempts a minute per email and IP pair, with Laravel's `RateLimiter`. On success: regenerate the session, set `last_login_at`, redirect an admin to `/admin` and a student to `/home` |
| `POST /logout` | Log out, invalidate the session, regenerate the CSRF token |
| `GET /` | `RootController` redirects by role |
| `POST /forgot-password` | Laravel's password broker. The same message whether the email is unknown, inactive or active (P30). An inactive user gets no link |
| `POST /reset-password` | Laravel's password broker. Refuses an inactive user. Sets the password and ends the user's other sessions |

`FirstAdminSeeder`: one admin from `.env` (through `config/`), `password` NULL,
`email_verified_at` set, with a QR credential. The first password is set through
the normal reset link, read from the log with `MAIL_MAILER=log`
([ACCOUNT-MANAGEMENT.md](ACCOUNT-MANAGEMENT.md)).

Tests (`tests/Feature/Identity/LoginTest.php`, `PasswordResetTest.php`):
- `test_it_signs_in_a_student_and_redirects_to_home`
- `test_it_signs_in_an_admin_and_redirects_to_admin`
- `test_it_rejects_an_unactivated_account_with_the_generic_message`
- `test_it_rejects_an_inactive_account_with_the_generic_message`
- `test_it_limits_login_to_five_attempts_a_minute`
- `test_it_sets_last_login_at`
- `test_it_gives_the_same_forgot_password_answer_for_any_email`
- `test_it_sends_no_link_to_an_inactive_account`
- `test_it_sets_a_password_from_a_valid_link`
- `test_it_seeds_one_unactivated_admin`

---

## Step A4 — Registration and email confirmation

New files:

- `Http/Controllers/Auth/RegisterController.php` — `show()`, `store()`
- `Http/Requests/RegisterRequest.php` — the rules in `API_Design.md` §7.6
  ("Register")
- `Http/Controllers/Auth/EmailVerificationController.php` — `notice()`,
  `verify()`, `send()`

Routes in `Identity/routes/web.php`, with the names from `API_Design.md` §7.2:
`/register` (guest, throttled 5 a minute per IP), and `/email/verify`,
`/email/verify/{id}/{hash}` (`signed`), `/email/verification-notification`
(throttled 6 a minute) for a signed-in user.

`store()`: `AccountService::register()`, then log the student in, then fire
`Registered` inside a `try`. Laravel's own listener sends the confirmation email;
if the send fails, the account stands and the notice page says to use the resend
button. Redirect to `/email/verify`.

Tests (`tests/Feature/Identity/RegistrationTest.php`):
- `test_it_registers_a_student_and_signs_them_in`
- `test_it_sends_the_confirmation_email`
- `test_it_keeps_the_account_when_the_email_fails`
- `test_it_requires_a_unique_student_number_and_email`
- `test_it_never_creates_an_admin_by_registration`
- `test_it_confirms_the_email_from_a_signed_link`
- `test_it_rejects_a_tampered_confirmation_link`
- `test_it_resends_the_confirmation_link`
- `test_it_limits_registration_to_five_attempts_a_minute`

---

## Step A5 — Admin user tools

`Http/Controllers/Admin/UserController.php`; the FormRequests exist.

| Action | Behaviour |
|---|---|
| `index` | Paginated list with organization eager-loaded (`with()`). Shows which accounts are not activated (`password` NULL) or not confirmed |
| `store` | `AccountService::createByAdmin()`, then send the set-password link through the password broker after the commit. A failed send keeps the account and tells the admin |
| `update` | `AccountService::update()` with the signed-in admin as actor |
| `sendPasswordLink` | Sends a new link; the broker keeps only the latest |
| `setPassword` | `AccountService::setPassword()` |
| `import` | `AccountService::import()`; on errors, back to the page with the row errors and nothing imported |

Tests (`tests/Feature/Identity/AdminUserTest.php`):
- `test_it_lets_only_admins_reach_the_user_pages`
- `test_it_creates_a_user_and_sends_the_set_password_link`
- `test_it_keeps_the_user_when_the_link_email_fails`
- `test_it_paginates_the_user_list`
- `test_it_sets_a_password_as_the_fallback`
- `test_it_reports_csv_row_errors_and_imports_nothing`

---

## Step A6 — Organizations

`Http/Controllers/Admin/OrganizationController.php` calls `OrganizationService`.
No delete route (P33); an organization is switched off with `is_active`.

Tests: `test_it_creates_an_organization`,
`test_it_requires_a_unique_code`,
`test_it_keeps_students_of_a_disabled_organization`.

---

## Step A7 — Profile and QR

`ProfileController::show()` shows the student's details, organization and QR
token as text. `regenerateQr()` calls `AccountService::regenerateQr()`. The
page is behind `verified`, so an unconfirmed student never sees a token (P48).

Tests: `test_it_shows_the_qr_token_to_its_owner`,
`test_it_regenerates_the_qr_token_and_the_old_one_stops_matching`.

---

## Step A8 — Landing pages (coordinate with their owners)

`/home` belongs to Rewards and `/admin` to Reporting. Until Track D fills
them, each shows the signed-in user's name, role and links to the working
account pages. Agree the file owner with the Rewards and Reporting writers
first ([TEAM-WORKFLOW.md](TEAM-WORKFLOW.md), "One writer per file").

---

## Views

Bare Blade from `resources/views/README.md`: `identity/auth/*`,
`identity/profile/show`, `identity/admin/users/*`,
`identity/admin/organizations/*`, plus the new `identity/auth/register` and
`identity/auth/verify-email`. Each is a plain form or table inside its layout,
with `@csrf`, `@method('PUT')` where needed, old input kept (never passwords),
and errors shown. No styling, no JavaScript.

## Done when

- Every step's tests pass, plus `php artisan test`, `./vendor/bin/pint --test`
  and `../scripts/check-swappability.sh`.
- A fresh install can seed the first admin, set its password from the logged
  link, create an organization, and a student can register, confirm and sign
  in.
- No controller contains a query or a rule; every write is in `AccountService`
  or `OrganizationService`.
