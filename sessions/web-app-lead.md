---
id: web-app-lead
created: 2026-10-04
updated: 2026-10-07
status: in-progress
---

## Goal

Lead web application delivery for smart-e-waste. Codex owns analysis, contracts,
task handoffs, technical review and integration coordination. Human teammates
implement and fix application code. Start with login and account management.

## Progress Log

- **2026-10-07:** Moved the recap to repository-root `sessions/`, corrected its
  three relative links and self-reference, and recorded this location for future
  recaps. Formatting, links and the old-path reference scan passed. No application
  code changed; today's relocation and recap update are not committed or pushed.
- **2026-10-04:** Completed the account design interview and team-readiness
  analysis. Saved agreed policies, terminology, file ownership, dependency order,
  acceptance and merge gates. Earlier deposit-related source edits were reverted
  after the user redirected scope to the web app. No application implementation,
  installations or remote-setting changes were made. Subsequently committed and
  pushed all four planning notes as `5e9c0b6` on `web/account-workflow`.
- **Current repository snapshot:** `web/account-workflow` at `5e9c0b6`, tracking
  `origin/web/account-workflow`. The recap now lives in `sessions/`; its former
  location is removed. This relocation is pending commit, not lost recap content.
  The three web planning files are committed and unchanged.
- **Verification:** Signing vectors and swappability passed on 4 October; they
  were not rerun for today's documentation-only work. PHP/Pint cannot run from
  this checkout without bootstrap. Today's recap checks cover formatting, local
  links and Git whitespace.

## Files Touched

- [web/docs/ACCOUNT-MANAGEMENT.md](../web/docs/ACCOUNT-MANAGEMENT.md): confirmed milestone
  decisions, scope and acceptance targets.
- [web/docs/CONTEXT.md](../web/docs/CONTEXT.md): account terminology.
- [web/docs/TEAM-WORKFLOW.md](../web/docs/TEAM-WORKFLOW.md): proposed human-team workflow.
- `sessions/web-app-lead.md`: this handoff.

## Decisions & Rationale

- Human teammates implement; Codex leads. Analysis agents are advisory support.
- Store future repository-local recaps in `sessions/`, following the requester's
  directory preference rather than the session-recap skill's default.
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

- Laravel skeleton/manifests are still absent. The 4 October runtime audit found
  PHP 8.2.12 below required 8.4; compatible Composer/database readiness was not
  established. Runtime installations were not rechecked today.
- Human role holders and area approvers remain unnamed. Confirmed account choices
  still need source-document alignment; do not reopen the settled interview.
- Existing device PHP tests are incomplete. No tracked CI/CODEOWNERS was found;
  remote protections and bypass rules were not inspected.
- Cross-module access, session-driver portability, old-email token invalidation,
  last-admin concurrency and backend/view handoffs require shared contracts.

## Next Steps

1. Commit and push the recap relocation/update when requested; include both the
   former-path deletion and `sessions/web-app-lead.md`.
2. Assign human implementation roles, the area reviewer and merge maintainer.
3. Hand confirmed account notes to the docs owner for reconciliation; have the
   foundation member refresh tooling readiness and bootstrap Laravel 13/PHP 8.4
   using SETUP alongside that source alignment.
4. Freeze account-policy and view contracts, then issue bounded human task packets
   with file ownership, base revision, dependencies and acceptance evidence.
5. Review human-authored PRs and integrate in dependency order with the required
   checks, MariaDB races, browser acceptance and owner approval; coordinate CI and
   protection verification with the maintainer.
