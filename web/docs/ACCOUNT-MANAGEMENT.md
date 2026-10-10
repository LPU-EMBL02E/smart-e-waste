# Login and Account Management

Draft notes from the web application design interview on 3 October 2026. The
choices below are confirmed by the requester for team review. Source-document
updates and owner review remain separate team work; `docs/` remains the project's
source of truth.

## First milestone

Deliver login and account management: student and admin access, account creation,
and password setup. Implementation and tests belong in `web/`.

Existing contracts are in [System_Plan.md](../../docs/System_Plan.md), sections 4 and
5.6, and [API_Design.md](../../docs/API_Design.md), sections 7.2, 7.4 and 7.6. Account
terms are defined in [CONTEXT.md](CONTEXT.md).

## Confirmed decisions

### Account onboarding

Keep the proposed account model: admins create accounts, users set their passwords
through emailed links, and admins can set passwords when email is unavailable.
There is no public self-registration. Account creation follows the existing
student-organization requirements.

### Deactivation during a session

An inactive account loses access on its next authenticated web request. This
applies to both students and admins, including accounts that are already signed
in when they are disabled.

### Prevent admin lockout

Block disabling or demoting the last admin whose account is active and activated.
This includes changes that admin makes to their own account. Another active admin
with a configured password must remain; an unactivated admin is not a replacement.

### First-admin activation

During first installation, use the existing password-reset flow with
`MAIL_MAILER=log`. The installing operator retrieves the setup link locally and
sets the first admin's password through the normal reset form. The seeder keeps
the account's password unset until that setup succeeds.

### Setup and reset link lifetime

Use a 60-minute lifetime for both password setup and reset links through the
shared password broker. A user can request another link after expiry.

### Invitations after CSV import

Keep the existing individual send/resend actions for this milestone. The import
itself sends no email; a bulk invitation action is deferred.

### Self-service editing

Defer signed-in users editing their own profile or password. Admins manage account
details, and users retain the existing guest-accessible forgot-password flow.

### Inactive accounts and password links

Inactive accounts cannot request or use password setup/reset links until an admin
reactivates them. Link-request responses remain generic for active, inactive and
unknown accounts.

### Sessions after account changes

After a password, email or role change, all previous sessions for that account
end on their next authenticated web request. Password changes include both
password-broker resets and passwords set by an admin.

### Login destinations for this milestone

Keep the existing login destinations, `/home` for students and `/admin` for
admins. For this milestone, show real account information and links to working
account tools. Full points, transaction and bin dashboards belong to later
milestones.

### Inactive organizations

Disabling an organization preserves its existing students' account status and
web access. Block new student assignments to an inactive organization, including
assignments through CSV import. An existing membership may remain unchanged when
an admin edits other account details.

### Outstanding password links

Revoke outstanding setup/reset links after password, email or role changes and
account deactivation. Resending a setup/reset link invalidates earlier links;
only the latest issued link remains usable.

## Implementation scope

- Install the committed Laravel 13 app with PHP 8.4 and Composer using
  `SETUP.md` §1–2.
- Implement login, logout, password setup/reset, and first-admin activation.
- Implement admin user and organization management, CSV import, individual
  invitations, and the manual password fallback.
- Implement the account-oriented `/home` and `/admin` landing pages described
  above using the existing routes.
- Use Blade and Tailwind, plain forms with CSRF protection, and paginated lists.
  Follow the repository's module ownership and transaction rules.

## Verification targets

- Guests are redirected to login; students cannot access admin routes; successful
  login reaches the correct working landing page.
- Unactivated and inactive accounts cannot sign in. Login failure messages and
  the documented five-attempts-per-minute limit are preserved.
- Logout invalidates the session and regenerates the CSRF token. Deactivation
  and password/email/role changes end previous sessions on the next authenticated
  request.
- Disabling or demoting the last active, activated admin is refused, including
  self-edits and concurrent attempts.
- User creation and CSV import create each user and QR credential together. CSV
  validation is all-or-none, reports row errors, and sends no invitation email.
- Mail failure preserves a newly created account and reports failed delivery to
  the admin. Individual invitations and the manual password fallback work.
- Setup/reset links expire after 60 minutes, reject inactive accounts, are
  superseded by resends, and are revoked after the agreed account changes.
- Inactive organizations reject new assignments while preserving existing
  memberships and student access.
- A fresh install can activate its first admin through a locally logged link.
- Run the relevant feature tests and Pint checks, plus the repository's required
  swappability and signing-vector checks.

## Design status

The requester has confirmed the decisions for this account-management milestone.
Human team members implement the application; the web app lead coordinates
contracts, task boundaries, reviews and integration. The delivery workflow is
recorded in [TEAM-WORKFLOW.md](TEAM-WORKFLOW.md).

The docs owner still needs to reconcile these decisions with the authoritative
source documents through a separate reviewed change. Installing the app with
`SETUP.md` can proceed while that alignment is completed. Owner review
remains part of the repository's PR process.
