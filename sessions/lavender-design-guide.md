---
id: lavender-design-guide
created: "2026-10-07"
updated: "2026-10-07"
status: done
---

## Goal

Create a practical DESIGN.md for Smart E-Waste UI designers and frontend
developers, using minimalist neobrutalism with lavender purple and white.

## Progress Log

- 2026-10-07: Created root DESIGN.md using the emil-design-eng and
  frontend-design skills. Grounded the guide in repository UI requirements and
  reviewed it against the source docs. It covers palette tokens, typography,
  spacing, component states, responsive layouts, page recipes, accessibility,
  motion, implementation boundaries, and a handoff checklist.
- Verification passed: UTF-8/LF/whitespace, local links, Markdown table and fence
  structure, eight palette contrast ratios, `git diff --no-index --check`,
  signing vectors, and `scripts/check-swappability.sh`. On Windows, the last
  check required Git Bash with `/usr/bin:/bin` added to its PATH. Prettier was
  unavailable; no dependency was added.
- 2026-10-07: Moved this recap to the root `sessions/` directory, the user's
  preferred location for repository session notes.

## Files Touched

- `DESIGN.md`: new UI design guide.
- `sessions/lavender-design-guide.md`: this handoff note.

## Decisions & Rationale

- White dominates; lavender emphasizes the main action and focal points panel.
  Black outlines and small hard shadows establish the neobrutalist identity.
- Lavender controls use black text because white text lacks sufficient contrast.
- Use native system fonts and CSS interaction feedback to avoid extra assets
  or libraries. Preserve Blade/Tailwind, ordinary forms, and dashboard-only JS.
- Existing business rules, API `[P*]` proposals, and `TODO(team)` decisions retain
  their authority and status. Firmware UI is outside this guide's scope.

## Open Issues / Blockers

No blockers for the completed document. DESIGN.md is untracked and uncommitted
on `docs/lavender-design-guide`; no commit, push, or PR was requested. HEAD remains
`107432d` (merge of PR #3). Existing `web-app-lead.md` was left untouched.
Browser, QR-scanner, and assistive-technology checks apply when UI is implemented.

## Next Steps

1. Have the designers and frontend developers review the guide before UI work.
2. If delivery is requested, obtain the required owner review and commit the
   intended files through a documentation PR.
3. When implementing screens, follow the guide and verify responsive behavior,
   keyboard access, contrast, reduced motion, and real data states in the browser.
