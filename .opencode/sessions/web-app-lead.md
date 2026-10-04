---
id: web-app-lead
created: 2026-10-04
updated: 2026-10-04
status: in-progress
---

## Goal

Lead web application delivery for smart-e-waste. Codex owns analysis, contracts,
task handoffs, technical review and integration coordination. Human teammates
implement and fix application code. Start with login and account management.

## Progress Log

- **2026-10-04:** Completed the account design interview and team-readiness
  analysis. Saved agreed policies, terminology, file ownership, dependency order,
  acceptance and merge gates. Earlier deposit-related source edits were reverted
  after the user redirected scope to the web app. No application implementation,
  installations, commits, pushes or remote-setting changes were made.
- **Repository snapshot:** Detached HEAD at `e75363f` (merge of the AGENTS rules
  PR). Recent history also contains `185d86c` and `120258c`. Tracked diff is empty;
  the three web planning files below are untracked. This recap adds one note.
- **Verification during analysis:** Signing vectors and swappability passed;
  Markdown encoding, whitespace and 33 local links passed. PHP application tests
  and Pint remain unavailable because Laravel is not bootstrapped and compatible
  tooling is not established. Runtime checks were not repeated for this recap.

## Files Touched

- [web/ACCOUNT-MANAGEMENT.md](../../web/ACCOUNT-MANAGEMENT.md): confirmed milestone
  decisions, scope and acceptance targets.
- [web/CONTEXT.md](../../web/CONTEXT.md): account terminology.
- [web/TEAM-WORKFLOW.md](../../web/TEAM-WORKFLOW.md): proposed human-team workflow.
- `.opencode/sessions/web-app-lead.md`: this handoff.

## Decisions & Rationale

- Human teammates implement; Codex leads. Analysis agents are advisory support.
- Admin-created accounts, individual 60-minute setup/reset links, manual password
  fallback and first-admin activation through locally logged mail. CSV import is
  atomic, creates user/QR together and sends no email.
- Unactivated means no password; inactive means disabled. Enforce next-request
  deactivation/session revocation, revoke outstanding password links after agreed
  mutations, and protect the last active admin with a password, including races.
- Inactive organizations reject new assignments while retaining memberships and
  access. Defer bulk invitations, self-editing and full dashboards. Keep existing
  `/home` and `/admin` module ownership. Profile/QR rendering deferral is a lead
  recommendation, not an additional confirmed product decision.
- Use one reviewed framework baseline, isolated member checkouts/test databases,
  exclusive shared-file ownership and agreed service/view contracts. `docs/`
  remains authoritative; preserve requester decisions through separate owner
  review rather than silently rewriting source specifications.

## Open Issues / Blockers

- No Laravel skeleton/manifests yet. Audited PHP 8.2.12 is below required 8.4;
  Composer availability and a MariaDB 11.8 test database are not established.
- Human role holders and area approvers remain unnamed. Confirmed account choices
  still need source-document alignment; do not reopen the settled interview.
- Existing device PHP tests are incomplete. No tracked CI/CODEOWNERS was found;
  remote protections and bypass rules were not inspected.
- Cross-module access, session-driver portability, old-email token invalidation,
  last-admin concurrency and backend/view handoffs require shared contracts.

## Next Steps

1. Assign human foundation/auth, admin-account and UI/verification roles plus the
   authorized area reviewer and merge maintainer.
2. Hand confirmed account notes to the docs owner for a separate reconciliation.
3. Have the foundation member establish PHP 8.4/Composer and the Laravel 13
   baseline using SETUP; preserve repository files and prove migration/route/build
   readiness. This can proceed alongside source alignment.
4. Freeze account-policy and view contracts, then issue bounded task packets with
   file ownership, base revision, dependencies and acceptance evidence.
5. Review focused human-authored PRs; require repository checks, MariaDB race and
   browser acceptance, and owner approval before ordered integration. Coordinate
   CI/protection verification separately with the maintainer.
