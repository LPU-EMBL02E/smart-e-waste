---
id: web-access-wireframes
created: "2026-10-17"
updated: "2026-10-17"
status: done
---

## Goal

Audit the web access flow against the supplied flowchart and deliver connected,
consistent guest, student and admin wireframes for human implementation.

## Progress Log

- **2026-10-17:** Audited both dashboards against PDF page 4. Added shared login,
  registration, pending/denied results, password setup/recovery and role handoffs.
  Admin review requires four school-record checks before approval; pending and
  denied applicants remain blocked. Recorded the revised policy and implementation
  handoff in the access plan.
- Applied one lavender/white/black stylesheet across 42 screens/states. Added
  native navigation/dialog controls, readable tables, persistent feedback and
  keyboard support. Corrected two leaderboard fixtures displaying `NaN`.
- **Verification:** 189 access assertions and 1,236 design assertions passed,
  covering 169 screen/viewport cases at 320, 390, 768 and 1280px, including 36
  keyboard-scroll cases. Both checks reported zero browser/runtime errors.
  Reviewed desktop/mobile renders; formatting, static references, swappability,
  signing vectors and Git whitespace passed.
- Delivery branch: `web/access-flow-plan`, based on `origin/main` at `3ded749`.
  This recap accompanies the wireframe package's commit and branch push.

## Files Touched

- [Access plan](../web/docs/ACCESS-FLOW-PLAN.md).
- [Shared access wireframe](../web/docs/wireframes/access-wireframe.html).
- [Admin wireframe](../web/docs/wireframes/admin-wireframe.html).
- [Student wireframe](../web/docs/wireframes/student-dashboard-prototype-notification-bell.html).
- [Shared styles](../web/docs/wireframes/wireframe.css).
- [Access checks](../web/docs/wireframes/access-wireframe.check.js) and
  [design checks](../web/docs/wireframes/design-wireframes.check.js).
- `sessions/web-access-wireframes.md`: this recap.

## Decisions & Rationale

- Store recaps in root `sessions/`, following the requester's override of the
  skill's default. Keep earlier session notes unchanged.
- The requester chose student self-registration with admin approval/denial.
  Verify name, student number, email and organization against school records.
  Approval is separate from enabled status and password setup.
- These are standalone prototypes with synthetic per-tab data. Passwords are
  discarded; recovery sends no email. Human teammates implement the application;
  no PHP, schema, dependency or authoritative source-document changes were made.

## Open Issues / Blockers

- No remaining prototype blocker. Production approval, audit retention,
  concurrency and correction/resubmission contracts need owner review; older
  authoritative documents still describe admin-created accounts.
- PHP tests/Pint cannot run without `web/vendor/`. Screen-reader and browser zoom
  checks remain unverified; wireframe checks do not establish backend readiness.

## Next Steps

1. Open the branch PR and obtain the web owner's review of the combined journey.
2. Have the docs owner reconcile the approved registration direction and audit
   rules before production implementation.
3. Assign human frontend/backend task packets with explicit route/view contracts.
4. Verify real authentication, approval, mail and concurrency in application tests.
