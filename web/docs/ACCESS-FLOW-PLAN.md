# Web access flow audit and plan

Working plan for the human web team, 17 October 2026. Reviewed against `main` at
`3ded749`. Codex coordinates the design, contracts and review; human teammates
implement the application. This note records wireframe decisions; it adds no
application routes or source-document approvals. The full operational dashboards
remain a later milestone.

## Requested registration revision — 17 October

The requester replaced the earlier admin-created-account direction with **student
self-registration followed by admin approval or denial**. They selected this
verification criterion: match the applicant's **name, student number, email and
organization against school records**. Email verification and document uploads
were not selected.

The current prototype journey is:

1. **Create account:** student enters first/last name, student number, email,
   organization, password and confirmation. There is no public admin-role choice.
2. **Pending admin review:** the request is saved as pending. The student has no
   portal access until approval. Existing approved account fixtures still sign in.
3. **Registration requests:** an admin sees the submitted details and checks all
   four against school records. The checks are manual; the prototype contacts no
   school system and cannot establish that the checks were actually performed.
4. **Approve or deny:** approval requires all four verification checkboxes. Denial
   remains available when details cannot be verified. The demo retains the
   decision, reviewer, UTC time, verification checks and optional applicant-facing
   note. A completed demo decision is displayed read-only.
5. **Result:** an approved applicant can continue to the student destination with
   their submitted identity. Pending and denied applicants remain blocked; a
   password-reset demo does not change approval. Denied applicants can read any
   review note and contact the administrator. Correction/resubmission policy is
   still a team decision; no reapplication workflow is invented here.

This revision supersedes the earlier onboarding direction **for the wireframes**.
[System Plan §4](../../docs/System_Plan.md#4-modular-monolith--module-breakdown),
[API §7.2/§7.6](../../docs/API_Design.md#7-web-routes),
[the schema](../../docs/Database_Schema.md#2-users), and the older
[account interview notes](ACCOUNT-MANAGEMENT.md#account-onboarding) still describe
admin creation and need a separately reviewed contract update before backend
implementation. Approval must be represented separately from enabled/disabled
status and whether a password exists; do not silently repurpose those fields.
Route names, schema changes, production audit retention and concurrency rules
remain human-team/doc-owner work. No PHP, source contracts or migrations change
in this wireframe revision.

The demo keeps one synthetic registration and its review per browser tab. Clear
it in **Prototype controls** to try another. Password inputs are discarded, never
stored or sent; applicant demo sign-in uses the fixed `wireframe-demo` password
after the user selects **Fill applicant demo sign-in**. This is a deliberate
prototype limit, not a production password rule. No real personal details belong
in the demo. All dashboard balances and activity remain illustrative fixtures.

## Shared wireframe implementation

The [shared access wireframe](wireframes/access-wireframe.html) is now the guest
entry for both existing dashboard prototypes. It uses the lavender, white and
black design guide and includes email/password sign-in, rejection and attempt-limit
feedback, password recovery and its generic acknowledgement, the shared setup/reset
form, validation, a labelled demo result, unusable links, access denial and a
connection-failure preview, plus the registration revision above.

Expand **Prototype controls** for the student/admin fixtures and state previews.
There is no role choice in the product form. Successful demo sign-in opens the
matching dashboard; both dashboards return guests and wrong-role fixtures to the
shared page. Their logout controls clear the role marker and return to sign-in,
including when a previous dashboard is revisited through browser history.

Synthetic identity, registration details and their review are stored in this tab's
session storage. No password is
saved, changed or sent. Recovery sends no email; link validity and server failure
are review states, not a running password broker or server. Attempt counts reset
when the page reloads. Production authentication, authorization, token handling,
mail and email/IP throttling remain human implementation work.

The [browser self-check](wireframes/access-wireframe.check.js) covers the guest
journeys, both dashboard handoffs and logout controls, role denial, history after
logout, validation, generic recovery, keyboard entry, skip navigation and the
shared states at 320, 390, 768 and 1280 CSS px. To replay from the repository root:

```powershell
py -m http.server 8765 --bind 127.0.0.1 --directory web/docs
```

In a second terminal, with Node.js and Playwright CLI/Chromium available:

```powershell
npx --package @playwright/cli playwright-cli -s=access-review open http://127.0.0.1:8765/wireframes/access-wireframe.html
npx --package @playwright/cli playwright-cli -s=access-review run-code --filename web/docs/wireframes/access-wireframe.check.js
npx --package @playwright/cli playwright-cli -s=access-review close
```

The earlier registration pass inspected desktop and mobile renders of registration,
pending status and admin review. The shared design pass below expands coverage to
the other dashboard screens. Legacy user-management actions remain design references.
The baseline audit below records the earlier prototypes;
its old line references describe the reviewed revision, not the current file layout.
The later planning sections also retain the earlier admin-onboarding proposal for
traceability; the registration revision above is the current requested wireframe.

The earlier registration revision passed **187 browser assertions with zero JavaScript
runtime or console errors**, including pending/denied access blocking, approval only after
all four checks, recorded review details, password-reset isolation, applicant
identity rendering, escaped submitted text and mobile admin navigation. The
initial shared-access version had passed 133 assertions. Prettier was applied
to the new files and modified scripts,
without reformatting the existing dashboard markup. Inline JavaScript syntax,
label/error references, local links, UTF-8/LF, signing vectors, swappability and
`git diff --check` passed. Laravel tests and Pint could not run because this
checkout has no `web/vendor/`; no PHP application files were changed.

## Shared design implementation — 17 October

All three prototypes now load [one shared stylesheet](wireframes/wireframe.css),
following [DESIGN.md](DESIGN.md). This covers 20 admin screens, 11 student views
and 11 access states, including registration and password setup/recovery. The
stylesheet owns the exact lavender/white/black palette, native font, type sizes,
spacing, borders, buttons, fields, statuses and responsive shells. The student
points panel is the focal accent; operational admin tables stay flat and readable.
The student home table spans the content width so its columns remain readable.

Both dashboards use native expandable navigation. Student links initially remain
visible on mobile; the longer admin navigation initially collapses below 1024px.
Tables have named, focusable scroll regions, numeric alignment and a mobile scroll
hint. Headings follow the active page, forms retain persistent labels, and keyboard
focus and reduced motion follow the guide. The existing reward confirmation uses
a native dialog with Cancel/Escape support, and feedback remains inline instead of
disappearing on a timer. The Settings reference uses the shared lavender/white theme.

Admin controls that only represented future actions are disabled and explained in
the prototype banner. The existing BIN-01 and transaction detail examples are
reachable from their matching sample rows. Registration review, role handoffs,
logout and the student demo actions remain connected. A visual check also corrected
two leaderboard fixture values that previously rendered as `NaN`; ranking rules
and production points logic are unchanged.

The [access self-check](wireframes/access-wireframe.check.js) passes 189 assertions.
The [design self-check](wireframes/design-wireframes.check.js) visits every screen
at 320, 390, 768 and 1280 CSS px, plus a confirmed demo redemption. It checks page
overflow, heading structure, labels, touch targets, table access and keyboard
scrolling, then exercises filters, dialog cancellation/confirmation, persistent
feedback, focus and reduced motion. Run it with the same isolated Playwright tab:

```powershell
npx --package @playwright/cli playwright-cli -s=access-review run-code --filename web/docs/wireframes/design-wireframes.check.js
```

Desktop/mobile renders of all 42 screens/states were captured for visual review.
The design pass reports 1,236 assertions over 169 screen/viewport cases, including
36 keyboard-scroll cases. Both browser checks report zero JavaScript runtime or
console errors. Prettier,
swappability, signing vectors and `git diff --check` pass. No dependency, PHP,
schema or authoritative source-document changes are included. Laravel tests and
Pint remain unavailable without `web/vendor/`; screen-reader and browser zoom
testing remain follow-ups. These files are prototypes; production account approval
and audit contracts still require the human team's implementation and review.

## Sources and scope

- **Flow reference:** `EMBL02E_Smart_Ewaste_System_Flowchart.pdf`, page 4, **Web
  Application Access Flow**. Supplied local reference: `C:\Users\vonac\OneDrive\Documents\school dox\EMBL01E\EMBL02E_Smart_Ewaste_System_Flowchart.pdf`.
  It shows desktop/mobile entry, credentials checked by the server against the
  database, invalid credentials returning to login, role-based access, and data
  shared with the machine. Its note 8 labels the invalid-credentials loop as an
  assumption. Registration, recovery, logout and the credential type are absent.
- **Behavior contracts:** [System Plan §4](../../docs/System_Plan.md#4-modular-monolith--module-breakdown),
  [API Design §7.1–7.6](../../docs/API_Design.md#7-web-routes) and
  [Project Guidelines, Step 8](../../docs/PROJECT-GUIDELINES.md#step-8--develop-the-web-application).
  Preserve the API document's `[P*]` proposal markers; this plan does not approve them.
- **Confirmed requester decisions:** [Account Management](ACCOUNT-MANAGEMENT.md)
  and [account vocabulary](CONTEXT.md). New policy alignment with authoritative
  `docs/` remains the docs owner's separate reviewed work; do not reopen settled decisions.
- **Presentation and implementation:** [Design Guide](DESIGN.md),
  [view conventions](../resources/views/README.md), [team workflow](TEAM-WORKFLOW.md)
  and [repository rules](../../AGENTS.md).

## PDF coverage in the two wireframes

These are illustrative prototypes. No server, real credentials, database or
production authorization is expected from them. A working click path demonstrates
the intended flow; it does not establish implemented authentication.

| Page 4 requirement                          | Admin wireframe                                                                   | Student prototype                                     | Next design work                                                                         |
| ------------------------------------------- | --------------------------------------------------------------------------------- | ----------------------------------------------------- | ---------------------------------------------------------------------------------------- |
| Open web app on desktop/mobile              | Starts at Flow/Sitemap inside admin navigation                                    | Starts at a guest login                               | Define one shared guest entry; verify both viewport sizes                                |
| Enter credentials; server checks database   | Email/password are static labels                                                  | Functional local demo using Student ID/password       | Use the documented email/password fields and label mock validation clearly               |
| Invalid credentials loop                    | Described in a note; no submitted/error state                                     | Incorrect demo values return to login with an error   | Show a generic error and correction/retry for either role                                |
| Valid credentials → access level            | `/admin` and `/home` redirects described                                          | Only the student destination is reachable             | Demonstrate both role outcomes from the same guest entry                                 |
| Student interface                           | Outside this wireframe                                                            | All eight PDF areas represented in navigation/content | Keep as later dashboard references; account milestone needs a working destination        |
| Administrator interface                     | Required areas represented, including total weight in transaction/dashboard views | Outside this prototype                                | Retain the admin references; prioritize account tools now                                |
| Display records from the machine's database | Placeholder values                                                                | In-memory mock values                                 | Backend implementation later supplies shared records; avoid presenting mock data as live |

Coverage evidence: [admin entry/navigation, lines 309–345](wireframes/admin-wireframe.html#L309),
[admin role flow, lines 364–378](wireframes/admin-wireframe.html#L364),
[admin login, lines 428–455](wireframes/admin-wireframe.html#L428),
[student state and screen names, lines 25–26](wireframes/student-dashboard-prototype-notification-bell.html#L25),
and [student login/render/logout, lines 49–55](wireframes/student-dashboard-prototype-notification-bell.html#L49).

## Actionable access-flow gaps

| Priority | Evidence                                                                                                                                                                                                                                                | Human team's correction in the next prototype                                                                                              |
| -------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------ |
| First    | [Student line 49](wireframes/student-dashboard-prototype-notification-bell.html#L49) accepts `studentId`; [API §7.2](../../docs/API_Design.md#72-guest-routes) and [LoginRequest](../app/Modules/Identity/Http/Requests/LoginRequest.php) require email | Align the guest form to email/password before frontend handoff; retain student number as account information                               |
| First    | [Admin lines 438–443](wireframes/admin-wireframe.html#L438) use `div`/`span` for fields, sign-in and recovery                                                                                                                                           | Add operable prototype form/navigation states so reviewers can complete sign-in, rejection and recovery; preserve its low-fidelity scope   |
| First    | [Admin lines 309–345](wireframes/admin-wireframe.html#L309) show the signed-in shell immediately; [student lines 49–52](wireframes/student-dashboard-prototype-notification-bell.html#L49) only lead to a student shell                                 | Give both prototypes a shared guest entry and explicit student/admin fixture outcomes; no role picker in the product                       |
| First    | [Admin lines 374–378](wireframes/admin-wireframe.html#L374) draw `/login` → forgot/reset → admin as one chain                                                                                                                                           | Draw successful login directly to a role branch; draw recovery as an optional branch returning to login                                    |
| Next     | [Admin line 443](wireframes/admin-wireframe.html#L443) only labels recovery; [student line 49](wireframes/student-dashboard-prototype-notification-bell.html#L49) has no recovery entry                                                                 | Provide link request, generic acknowledgement, shared setup/reset form and unusable-link recovery states                                   |
| Next     | [Admin line 334](wireframes/admin-wireframe.html#L334) switches sections; [top-bar line 345](wireframes/admin-wireframe.html#L345) is static; [student line 55](wireframes/student-dashboard-prototype-notification-bell.html#L55) simulates logout     | Demonstrate logout → guest entry and protected-page denial in both role flows; production uses the existing POST logout route              |
| Next     | [Admin user tools, lines 802–887](wireframes/admin-wireframe.html#L802) show creation, invitations, CSV and fallback mainly as static controls                                                                                                          | Connect the account-onboarding states below, including validation and delivery failures, without adding public signup                      |
| Next     | At 390px the admin Flow/Sitemap document is 459px wide; [fixed sidebar, lines 38–45](wireframes/admin-wireframe.html#L38), and [top-bar layout, lines 89–99](wireframes/admin-wireframe.html#L89) have no mobile treatment                              | Adapt the shared admin shell for narrow screens and contain wide content; repeat the access journey without page-wide horizontal scrolling |

## Browser review evidence

Reviewed through a loopback static server with Playwright on 17 October 2026;
desktop screenshots at 1280px and mobile checks at 390px. The PDF's page 4 was
also inspected visually. All eight student and nine admin feature groups have
screen-level representations; these checks establish prototype coverage only.

- Student: initial login, incorrect-credential retry, successful demo login,
  mobile navigation and return to login on logout worked. All eight PDF
  destinations are represented in navigation and source screens; they were not
  individually traversed in the browser. At 390px the document/body width was
  390px, with no page-wide overflow.
- Admin: initial Flow/Sitemap and full navigation, section-only Logout/Login,
  and static login/recovery controls were confirmed. At 390px the document/body
  width was 459px, producing 69px of page-wide overflow.
- Real authentication, mail, broker expiry, CSRF, database authorization, other
  viewport sizes, zoom and screen-reader behavior remain implementation checks.

## Smallest screen and state set

Use the existing `/login` for a combined welcome and sign-in page: a short purpose
statement, email/password form, **Forgot password?**, and account-access guidance
to contact the institution's administrator. No separate `/landing`, change to the
protected `/` redirect, public **Create account** button or role-selection control
is needed. Roles come from the authenticated account. This reuses the
[existing Identity routes](../app/Modules/Identity/routes/web.php) and
[root redirect](../app/Modules/Identity/Http/Controllers/RootController.php).

| Surface                                 | Required states and navigation                                                                                                                                                                                                                   |
| --------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `/login`                                | Initial form; required/format validation; generic credential rejection for wrong, unknown, unactivated or inactive account; documented five-attempts-per-minute email/IP limit; success to `/home` or `/admin`                                   |
| `/forgot-password`                      | Email form; validation; the same acknowledgement for active, inactive and unknown accounts; return to sign-in. Explain the next step without confirming account existence or delivery                                                            |
| `/reset-password/{token}`               | One shared form with **Set your password** for initial setup and **Reset your password** for recovery; password/confirmation validation; success with a sign-in link; invalid, expired, superseded or revoked link with **Request another link** |
| Existing error treatment                | Access denied for a student opening admin pages, with a route back to their destination; guest/session-expiry return to sign-in; server failure with a usable recovery link. Do not turn a failed request into success                           |
| `/home` and `/admin`                    | Real account identity and role, logout, and links to working account tools for that role. Full points, history, bins, rewards and reports stay in their later milestone                                                                          |
| Admin user list and create/edit         | Existing fields, enabled status and activation shown separately; field errors with safe old input; creation success; account preserved plus delivery warning when invitation mail fails; edit/save/cancel and individual send/resend result      |
| Admin manual password fallback          | Existing user action with password/confirmation and errors/result; available after an account exists, not a password requirement on the initial New user form                                                                                    |
| Admin CSV area                          | File input; exact documented header guidance; row errors and zero imports on any invalid row; successful import with count and clear no-email outcome; individual invitations afterwards                                                         |
| Admin organization list and create/edit | Existing fields and enabled state; validation/result; inactive membership retained, but unavailable for new student assignments                                                                                                                  |

These are states of the existing pages, not new endpoint requests. Route names,
password-broker use and account field rules come from [API §7.2, §7.4, §7.6](../../docs/API_Design.md#7-web-routes).
Reuse `password.reset` and the shared setup/reset broker; do not split activation
into a separate registration route. Keep broker-required email/token transport
with the backend contract. Setup and reset links last 60 minutes; resends supersede
earlier links; inactive accounts cannot request/use them. User-facing request
acknowledgements stay generic ([confirmed account decisions](ACCOUNT-MANAGEMENT.md)).

**Account registration means admin onboarding here:** admin **New user** → server
creates user and QR credential together → individual invitation → user sets a
password → user signs in. A new account is unactivated until a password is set;
inactive means disabled and is a separate state. Admin password fallback completes
setup when email is unavailable. First-admin setup uses the locally logged link.
Public self-registration would need an explicit policy/source decision and is
outside this agreed milestone ([System Plan §4](../../docs/System_Plan.md#4-modular-monolith--module-breakdown),
[Account Management](ACCOUNT-MANAGEMENT.md), [vocabulary](CONTEXT.md)).

## Build order and human handoffs

The next deliverable is an interactive wireframe package, before application
implementation. Keep prototype fixtures explicitly labelled; they are not
authentication, a mail service or a database.

| Package                  | Proposed files                                                | Human handoff                                                                                                                                                                                                                                                                     |
| ------------------------ | ------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| W1: shared guest journey | New `web/docs/wireframes/access-wireframe.html`               | Designer defines one welcome/login surface, forgot-password request/acknowledgement, shared setup/reset form, success and unusable-link states. Frontend teammate connects the states and two labelled account fixtures to the existing role destinations; no product role picker |
| W2: admin onboarding     | Existing `admin-wireframe.html`                               | Connect New user, validation, created/invitation result, delivery warning, resend and password fallback. Move guest entry out of the signed-in shell and link to W1; leave other feature areas as references                                                                      |
| W3: student handoff      | Existing `student-dashboard-prototype-notification-bell.html` | Align credentials with email/password, connect shared entry/recovery and logout, and preserve the eight student destination references                                                                                                                                            |
| W4: flow review          | Those three prototype files                                   | Recheck invalid retry, both role outcomes, activation, recovery and logout at desktop/mobile sizes; fix the admin overflow and verify labels, keyboard focus and error recovery                                                                                                   |

Build W1 first, then assign one writer for each existing prototype during W2/W3.
The lead reviews the combined journey against PDF page 4 and the account plan.
Only after that wireframe handoff should the human team implement the existing
Laravel routes and templates in the following order:

1. **Lead + human backend owner:** freeze each existing route's fields, view data,
   safe old input, errors, flash result and destination. Carry the already confirmed
   policies to the docs owner's separate source-alignment review. Do not hide a
   new policy or endpoint in a mockup.
2. **Human UI designer:** produce the shared guest entry and three guest forms,
   their error/success states, and both role destinations first; then connect the
   existing user/organization tools. Use the lavender/white/black design system,
   persistent labels, visible keyboard focus and mobile layouts from [DESIGN.md](DESIGN.md).
3. **Human frontend implementer:** build the existing guest/student/admin Blade
   shells and agreed view files using Tailwind and ordinary CSRF-protected forms.
   Preserve email on error, never passwords. Prototype fixture controls must be
   visibly separate from product UI; JavaScript demos do not become application
   authentication or scripted CRUD ([view conventions](../resources/views/README.md)).
4. **Human backend implementer:** implement Laravel guard/broker behavior and
   shared Identity account safeguards; then user/organization management, invitations,
   fallback and CSV. Coordinate the existing Rewards `/home` and Reporting `/admin`
   controllers with their assigned human writers ([team workflow](TEAM-WORKFLOW.md)).
5. **Human verification + lead review:** exercise the scenarios below, record the
   actual revision/evidence, and return concrete failures to the responsible human.
   An interactive prototype review and production feature-test pass are separate gates.

For the next prototype handoff, link shared guest screens to both existing role
prototypes and link both logout actions back. Demonstrate errors with clearly
labelled synthetic fixtures or review controls. Keep production access decisions
on the server; do not add a student/admin choice to the sign-in form.

## Acceptance scenarios

| Scenario                            | Expected result                                                                                                                                                                                                                                                                          |
| ----------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Student happy path                  | Guest opens protected page → shared login → valid email/password → `/home` → available student account view → logout → guest login; protected page cannot be revisited while signed out                                                                                                  |
| Admin happy path                    | Shared login → valid admin → `/admin` → Users → New user → invitation result → logout → guest login; protected admin page cannot be revisited while signed out                                                                                                                           |
| Student sad path                    | Wrong credentials retry at login with a generic message; unactivated/inactive accounts also fail; valid student opening `/admin/users` gets 403 and can return to `/home`                                                                                                                |
| Admin sad path                      | Wrong/inactive/unactivated admin login uses the same rejection; attempt limit is visible without revealing account state; an expired/revoked session returns to sign-in on the next protected request                                                                                    |
| Activation and recovery, both roles | Valid setup/reset link → matching valid password → success → login with new password; mismatch/invalid password preserves no secret input; expired/invalid/resend-superseded/revoked links fail and offer another request; unknown/inactive requests receive the generic acknowledgement |
| Onboarding failures                 | Duplicate/invalid user fields show errors; mail failure leaves the created user visible and unactivated with an admin warning and resend/fallback path; CSV row failure imports nothing, valid CSV imports without mail                                                                  |
| Shared account safeguards           | Deactivation or password/email/role change ends old sessions on the next authenticated request and revokes outstanding links; last active, activated admin cannot be disabled/demoted, including concurrent edits                                                                        |
| Organization and installation       | Inactive organization rejects new assignment/import but retains existing membership and access; fresh-install operator activates the first admin using the logged setup link                                                                                                             |
| Responsive and accessible review    | Complete each role's guest/login/error/recovery/logout journey at 320, 390, 768 and 1280 CSS px; keyboard order, labels, visible errors, focus, zoom and contained table scrolling remain usable                                                                                         |

Acceptance derives from [Account Management verification targets](ACCOUNT-MANAGEMENT.md#verification-targets),
[API §7](../../docs/API_Design.md#7-web-routes) and [Design Guide §9–11](DESIGN.md#9-accessibility-requirements).
Human implementation PRs need behavior tests plus the repository's required
swappability, signing-vector, Pint and application-test results; record blocked or
incomplete checks honestly ([AGENTS §6, §8](../../AGENTS.md)). This planning change
does not establish runtime readiness, working mail or real database authorization.
