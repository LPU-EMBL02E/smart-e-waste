# Web application team workflow

Lead analysis, 4 October 2026. This is a proposed working agreement for the web
team. It does not change application behavior, repository rules, or GitHub
settings. The aim is to expose blockers early and keep concurrent work isolated.

The current milestone is login and account management, as recorded in
[ACCOUNT-MANAGEMENT.md, lines 8–101](ACCOUNT-MANAGEMENT.md). Later points, bin,
deposit, redemption, and reporting work follows the existing project design.

## Responsibilities and readiness

Codex is the web lead: it owns analysis, contracts, task packets, technical
review, and integration coordination. It does not author application code.
Human teammates implement and fix the code in their task packets. The Codex
agents supporting this analysis are not the implementation team. Assign these
recommended human roles from the actual roster; roles can be combined.

The lead keeps one backlog and decision record, assigns dependencies and file
ownership, reviews submitted changes, and returns concrete findings to the human
implementer. Human teammates own implementation, test execution, fixes, and PR
submission; the authorized maintainer owns merging and deployment.

| Role | Work | Boundary |
|---|---|---|
| Foundation/auth core | Laravel bootstrap, authentication, password flow, shared account safeguards, and behavior tests | One assigned human writer for shared account primitives and configuration |
| Admin accounts | User/organization management, CSV import, invitations, password fallback, and behavior tests | Uses agreed account services; owns shared admin-controller handoffs |
| UI/verification | Blade/Tailwind account pages, independent acceptance, database/concurrency checks, and fresh-install evidence | Uses agreed view data; reports backend failures to its human owner; coordinates shared fixtures |

Current readiness from the team audit:

- `web/` has no Laravel framework skeleton or Composer/npm manifests yet. This
  is intentional, and bootstrap is already specified in
  [SETUP.md §1–3, lines 12–184](../SETUP.md). A member can prepare that foundation
  while source-document alignment proceeds separately.
- The runtime target is PHP 8.4, Laravel 13, and MariaDB 11.8. The discovered
  XAMPP PHP is 8.2.12 and its MariaDB is 10.4.32; neither satisfies that target.
  MySQL 8.0.41 was detected, but no project test database readiness is established.
  Follow the existing version policy rather than silently changing the stack
  ([SETUP.md, lines 29–58](../SETUP.md)).
- Signing vectors passed using bundled Python. Swappability passed with Git
  Bash's utility PATH set explicitly. Bare Bash previously returned exit 0 after
  a missing utility caused an erroneous skip; meaningful output matters as well
  as the exit code. These checks do not establish that the app runs.
- Both tracked PHP test methods still mark themselves incomplete
  ([DeviceSignatureTest.php, lines 46–69](tests/Feature/Device/DeviceSignatureTest.php)).
  No PHP application suite has been established as clean.
- No tracked CI workflow or CODEOWNERS was found in this checkout. Remote rules,
  repository visibility, plan, and bypass permissions were not inspected.
- The account plan and glossary are untracked interview notes. Requester choices
  are settled, but new account policies need alignment with authoritative
  `docs/` through its owner's separate review. Existing specified work can proceed
  ([ACCOUNT-MANAGEMENT.md, lines 3–6](ACCOUNT-MANAGEMENT.md);
  [AGENTS.md §1, lines 18–37](../AGENTS.md)).

## Contracts to align before feature work

Give the docs owner the confirmed account notes as one handoff. The source update
must record the active/activated distinction, next-request deactivation and
session revocation, usable last-admin protection, the first-admin setup flow,
60-minute links and their revocation, inactive-organization membership rules,
individual invitations, and the agreed self-editing and dashboard deferrals.
This preserves the interview decisions without asking teammates to decide them
again. The separate source update includes the interim behavior of the existing
`/home` and `/admin` destinations; their full dashboards remain later work.

Freeze these handoffs before separate members implement callers:

| Handoff | What the lead requires |
|---|---|
| Shared account policy | One Identity-owned service boundary for account mutations; controllers use Laravel's guard/broker and the same policy rather than copying rules. Agree inputs, outcomes and transaction boundaries before splitting auth and admin tasks. |
| Authenticated access | Apply next-request access/session policy across every authenticated web module, including Rewards and Reporting; an Identity-only check cannot cover the application. Test a revoked session against another module's route. |
| Password links | Include broker resets, admin password changes, email/role edits, deactivation and resend. Email edits invalidate tokens for the old address because tokens are keyed by email. Preserve session-driver swappability; do not depend only on deleting database sessions. |
| Account creation and import | Share user/QR creation without implicitly sending invitations. Mail follows account commit; delivery failure preserves the account and produces an admin warning. CSV stays all-or-none and sends no mail. |
| Organization assignments | Existing inactive membership remains available for unrelated edits; new assignments fail. Check organization deactivation racing with account creation/import. |
| Backend and views | Record the existing route and view name, method, fields, view-data shape, pagination, old input, field/CSV-row errors, redirect, and success/delivery-warning flash outcome for each page. Use the shared view tree already described in the views README. |

These contracts come from [ACCOUNT-MANAGEMENT.md](ACCOUNT-MANAGEMENT.md),
[API_Design.md §7.2–7.6](../docs/API_Design.md),
[System_Plan.md §4 and §8.1](../docs/System_Plan.md),
the [password-token migration](database/migrations/0001_01_01_000002_create_password_reset_tokens_table.php),
and the [view conventions](resources/views/README.md). Members propose the
smallest implementation within the existing schema. A new column, endpoint,
dependency, or unsupported outcome follows the owner decision path in AGENTS;
the lead records that decision before changing another task's contract.

## One writer per file

Use an isolated branch/checkout, local environment, and disposable test database
for each implementation member. Start from a reviewed foundation commit.
Separate test databases prevent ordinary parallel test runs from resetting each
other's data; deliberate concurrency workers share only their scenario database.

Keep one named writer at a time for bootstrap/configuration, `User`, account
middleware, module routes/provider registration, migrations, manifests/lock
files, shared fixtures, and the shared Blade layout. Record file ownership in the
task packet. Another member requests a handoff before editing those files. Add
new migrations when needed; never rewrite a merged migration
([AGENTS.md §4.3, lines 150–158](../AGENTS.md)).

Auth, password links, and admin account changes share revocation behavior. Agree
that service boundary before separate members work on callers. User management
and CSV import share `UserController`; assign them to one writer or sequence
their handoff. UI work can proceed in separate view files after route names,
view data, validation errors, and flash outcomes are agreed.

## Dependency order

| Package | Owner role | Depends on / completion evidence |
|---|---|---|
| Foundation | Foundation/auth core | Follow SETUP; preserve project files; register provider, middleware, auth model, and UTC connection; lock the generated baseline; demonstrate route/migration/build readiness |
| Auth and account safeguards | Foundation/auth core | Foundation; source alignment for new policies; login/logout, setup/reset, first-admin activation, and shared revocation behavior with tests |
| Users and organizations | Admin accounts | Shared account interfaces; transactional user/QR creation, invitations, password fallback, status/role safeguards, and organization rules |
| CSV import | Admin accounts | User-creation rules and an explicit handoff of shared files; all-or-none validation and no invitation as a side effect |
| Account UI and landing pages | UI/verification | Foundation layout and agreed view contracts; may run alongside backend packages in disjoint files; basic `/home` and `/admin` account tools |
| Integrated acceptance | UI/verification | Implemented packages; fresh install, browser checks, MariaDB races, full check report, and owner review |

Preserve existing module placement: `/home` belongs to Rewards and `/admin` to
Reporting ([API_Design.md §7.3–7.4](../docs/API_Design.md)). Those controllers need
explicit task ownership even during this account milestone. The lead recommends
deferring profile/QR rendering UI and its unresolved QR package; retain existing
route contracts and generate account QR credentials as required. Do not quietly
add a dependency or link an unfinished page as a working account tool
([API_Design.md §7.6 and §10 q8](../docs/API_Design.md);
[ACCOUNT-MANAGEMENT.md, lines 72–101](ACCOUNT-MANAGEMENT.md)).

## Task packet and handoff

Each task uses one short record: objective and exclusions; source sections and
agreed policy; base commit and dependencies; implementation owner and reviewer;
allowed/shared files; route/service/view contract; acceptance cases and test
data; required checks and unresolved decisions.

Completion returns changed files, commit/revision, behavior evidence, exact
commands/results, and remaining blockers. Label every check `passed`, `failed`,
`blocked`, or `incomplete`. Include test counts; zero tests or missing tools is
not verification. Business logic needs tests in the same focused change
([AGENTS.md §6, lines 234–244](../AGENTS.md)).

Use the stages Ready, Implementing, Review, and Integrated. Ready means the task
has an owner, an approved contract, a reviewed base commit, and available
dependencies. A blocked task records the evidence, affected contract/files,
decision owner, and next action; unrelated ready work continues. Before starting
and when returning a handoff, report scope, revision and blockers in the shared
backlog. Put decisions there rather than leaving them only in private chats.

Move to Integrated only after the maintainer merges the reviewed change and its
checks run on the resulting revision. If integration fails, pause dependent
merges, assign the failing package's human owner, and repair or revert the
affected focused change through the same review path.

Use synthetic users and organizations, active/inactive states, null/set
passwords, at least two usable admins, and duplicate/invalid CSV samples. Use
controlled clocks, fake mail or the local log mailer, and real session behavior.
Do not copy production data or send acceptance emails to real recipients. Keep
runtime credentials and generated reset links out of committed fixtures/logs;
the existing public signing vectors remain unchanged
([AGENTS.md, lines 66–67 and 241–242](../AGENTS.md)).

## Acceptance and merge gates

Use [ACCOUNT-MANAGEMENT.md, lines 104–125](ACCOUNT-MANAGEMENT.md) as the milestone
acceptance matrix after source alignment. Cover authorization and login limits;
two-session revocation; last-admin protection; expiry/resend/revocation of links;
atomic user/QR creation; invalid CSV rollback and no mail; mail failure after
creation; inactive organizations; and first-admin activation from a logged link.
Browser acceptance checks plain CSRF forms, errors/old input, pagination,
responsive layout, and functional landing-page links.

Run fast feature checks during work and the required repository checks before a
web PR: swappability, signing vectors, Pint, and `php artisan test`
([AGENTS.md §8, lines 263–277](../AGENTS.md)). Also inspect `route:list` after
bootstrap/auth/route changes and build assets when frontend files change
([SETUP.md, lines 233–241 and 274–280](../SETUP.md)). Format only changed files.
Track baseline incomplete tests and resolve their missing assertions with the
relevant owner; do not skip, delete, or weaken them or invent a full-suite
exemption. A passing account subset is reported as a subset.

Use MariaDB 11.8/InnoDB with `utf8mb4_unicode_ci`, UTC, and the default database
session driver for integrated checks. Verify unique keys, foreign keys, rollback,
and persistent sessions against that engine, not just SQLite
([Database_Schema.md, lines 5–13 and 24–51](../docs/Database_Schema.md);
[.env.example, lines 35–57](.env.example)). Preserve the documented session-driver
swappability in the account design
([System_Plan.md §8.1, line 656](../docs/System_Plan.md)).

Prove last-admin races with committed setup data and separately connected
workers synchronized at the competing operation. Assert that concurrent
disable/demotion attempts leave an active admin with a password. Bound waits
and inspect final rows. Sequential requests, isolated per-worker databases, or
one rollback-wrapped test do not prove this race safe. Use a concurrent-capable
server for HTTP races. Add a MySQL compatibility acceptance pass before handover.
Later ledger and stock races follow the existing locking rules
([System_Plan.md §6–6.1, lines 439–480](../docs/System_Plan.md)).

The lead recommends merging dependency PRs serially. Members rerun relevant
gates and the final required checks on the latest integrated revision after
base changes. Require an area-owner approval and the AI-assistance/performance
section; the team must name the human owner/reviewer currently marked `TODO(team)`
([AGENTS.md §9–10, lines 283–319](../AGENTS.md)). Codex technical review does not
automatically supply a GitHub approval identity or area-owner approval. The lead
reviews and recommends; the authorized repository maintainer merges.

## GitHub enforcement to prepare separately

These are recommendations for the repository maintainer and UI/verification role,
not settings already enabled or changes made by this analysis.

- Put valid owners on the PR base branch and protect the ownership file itself.
  CODEOWNERS requests review; it does not alone require approval. Owners need
  write access, and one listed owner can satisfy the requirement. Enable required
  owner review separately. [GitHub: code owners](https://docs.github.com/en/repositories/managing-your-repositorys-settings-and-features/customizing-your-repository/about-code-owners)
- Verify repository visibility and plan before choosing enforcement: code owners
  and protected branches are available for public Free repositories; private
  repositories need a supported paid plan. Require PR review, current checks,
  and renewed approval after changed code; inspect admin/bypass exceptions and
  use unique check names. These controls still need verification for this repo. [GitHub: protected branches](https://docs.github.com/en/repositories/configuring-branches-and-merges-in-your-repository/managing-protected-branches/about-protected-branches)
- Required checks must describe the latest revision. GitHub accepts successful,
  skipped, and neutral conclusions. A whole workflow skipped by filters/skip
  messages stays pending, but conditional jobs can report success without
  running; dependent jobs can also skip after failure. Plan a required
  gate that always evaluates prerequisite results and fails for applicable
  missing/incomplete checks. Keep optional non-applicable work distinguishable
  from mandatory work that did not execute. [GitHub: required status checks](https://docs.github.com/en/pull-requests/how-tos/merge-and-close-pull-requests/troubleshooting-required-status-checks)

## Later server/device coordination

Keep firmware implementation outside the web team's scope. Before later device
packages, coordinate the existing signing inputs/vectors, JSON errors and
retryability, units/null fields, device scoping, retries/duplicates, expiry, and
delayed completion with the docs and firmware owners
([API_Design.md §3–5 and §8](../docs/API_Design.md)). Use the existing simulator
before hardware. Its response comparisons need server-side database assertions
to establish a single transaction/credit
([simulate.py, lines 223–244](../tools/simulator/simulate.py)).

Timing, physical recovery, reviewed-session actions, new schema/API behavior,
points, and signing changes require the existing owner decision path. Do not
resolve those as side effects of account work
([API_Design.md §10](../docs/API_Design.md);
[AGENTS.md §11, lines 323–334](../AGENTS.md)).
