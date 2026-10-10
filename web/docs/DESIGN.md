# Smart E-Waste UI Design Guide

Minimalist neobrutalism in lavender, white, and black.

This guide is for UI designers and frontend developers building the student and
admin web interfaces. Use it for mockups, Blade components, and visual review.
It defines presentation, not new product features or business rules.

## 1. Scope and sources

Read this guide alongside the project contracts:

| Source | What it owns |
| --- | --- |
| [Project Guidelines](../../docs/PROJECT-GUIDELINES.md), Step 8 | Required student/admin interfaces and mobile support |
| [System Plan](../../docs/System_Plan.md), §§3, 5.3, 5.6–5.7 | Frontend stack, deposit rules, page scope, and points |
| [API Design](../../docs/API_Design.md), §7 | Routes, form responses, dashboard data, and validation |
| [Database Schema](../../docs/Database_Schema.md) | Field names, units, and stored states |
| [Blade views guide](../resources/views/README.md) | Layout shells, view naming, time display, and tables |
| [AGENTS.md](../../AGENTS.md) and [Setup](../../SETUP.md) | Implementation rules and build process |

Those documents govern behavior. This guide does not resolve `TODO(team)` items
or approve the API document's `[P*]` proposals. Keep their proposal status when
referencing them in designs. If a visual design needs an undocumented action,
field, metric, or state, resolve it with the team before implementation.

The hardware display is a separate firmware interface. This guide covers the
website; it does not prescribe the bin's screens or touch behavior.

## 2. Visual direction

**Make it feel like a clear collection receipt: bold totals, legible records,
and a lavender stamp of emphasis.** Students should quickly find their points,
QR code, and rewards. Administrators should quickly spot bin status and work
that needs attention.

- **Minimalism controls the layout.** Use white space, a clear reading order,
  and one dominant action per task. Keep everyday data easy to scan.
- **Neobrutalism controls the accents.** Use black outlines, bold type, squared
  shapes, and hard offset shadows. Keep everything aligned and upright.
- **Lavender directs attention.** Reserve the strongest lavender for primary
  actions, selected navigation, and one focal panel where it helps the task.
- **White does most of the work.** Aim for roughly three quarters of the visible
  surface to remain white. This is a composition guide, not a pixel quota.
- **Structure earns its place.** Add a border to group content or identify a
  control. Use spacing and headings before adding another box.

Use the points summary as the student home page's strongest visual element.
On admin pages, let operational status take priority. Avoid turning every metric
into an equally prominent card.

| Use | Avoid |
| --- | --- |
| Flat lavender fills, white surfaces, black text | Gradients, glass effects, glow, textured backgrounds |
| A few hard shadows that establish hierarchy | Blurred shadows, floating cards everywhere |
| Straight edges with small, consistent rounding | Pill buttons, large rounded containers, tilted cards |
| Real totals, units, dates, and clear action labels | Decorative counters, fake charts, invented impact claims |
| Quiet content with one strong focal point | Sticker collages, starbursts, emoji navigation, bouncing UI |

## 3. Color system

Use these token names in design files and implementation. These are the six
base colors; do not introduce nearby shades for individual pages.

| Token | Hex | Role |
| --- | --- | --- |
| `paper` | `#FFFFFF` | Page background, fields, tables, and ordinary panels |
| `ink` | `#000000` | Main text, borders, icons, hard shadows, and focus outlines |
| `lavender` | `#C4B5FD` | Primary buttons and the focal summary panel |
| `lavender-strong` | `#A78BFA` | Primary button hover/pressed fill; small emphasis areas |
| `lavender-wash` | `#F5F1FF` | Selected navigation and quiet table headers |
| `muted` | `#595463` | Supporting text on white or lavender-wash only |

Lavender is a background color, not a body-text color. Put **black text** on
lavender and lavender-strong. Do not use white or muted text on either fill.
Lavender alone is too faint against white to identify a control: keep its black
border. Inline links are black and underlined.

### Semantic accents

Use color sparingly for meaning. Keep status badges on white with a written
label; use the semantic color for their text, border, and optional icon.

| Token | Hex | Presentation role |
| --- | --- | --- |
| `success` | `#166534` | A server-confirmed successful or fulfilled outcome |
| `warning` | `#92400E` | Pending work, review, or attention |
| `danger` | `#B91C1C` | Errors, failures, and destructive-action emphasis |

Neutral or unknown information uses ink on paper or lavender-wash. Lavender
does not mean success. Never infer business status from a color or invent a new
status to fit a badge. Use the documented state and a readable label.

### Approved text pairs

Ratios below are calculated from the opaque hex values, rounded for display.
Recheck contrast if opacity, backgrounds, or colors change.

| Foreground / background | Contrast |
| --- | --- |
| Ink / paper | 21.00:1 |
| Ink / lavender | 11.38:1 |
| Ink / lavender-strong | 7.72:1 |
| Muted / paper | 7.31:1 |
| Muted / lavender-wash | 6.58:1 |
| Success / paper | 7.13:1 |
| Warning / paper | 7.09:1 |
| Danger / paper | 6.47:1 |

## 4. Typography

Use one native sans-serif family throughout:

```css
font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
```

This keeps the interface readable without a font download. Its personality
comes from heavy headings, strong numerical hierarchy, and disciplined spacing.
Use the target platform's system font in mockups and check fallback wrapping in
the browser; a downloaded display font is not required.

| Role | Mobile / desktop size | Weight | Line height |
| --- | --- | --- | --- |
| Focal points total | 2.5rem / 3rem | 800 | 1.1 |
| Page title | 1.75rem / 2rem | 800 | 1.2 |
| Section heading | 1.25rem / 1.5rem | 700 | 1.3 |
| Body and form input | 1rem / 1rem | 400 | 1.5 |
| Button and field label | 1rem / 1rem | 600 | 1.4 |
| Table text and helper text | 0.875rem / 0.875rem | 400 | 1.5 |

- Use sentence case. Reserve uppercase for actual abbreviations such as QR.
- Use one `h1` per page, followed by a logical heading order. Size does not
  determine heading semantics.
- Left-align headings, body copy, labels, and ordinary values. Right-align
  numeric table columns. Use `font-variant-numeric: tabular-nums` for totals,
  weights, points, and table numbers.
- Keep reading text around 60–70 characters wide. Never justify paragraphs.
- Use `-0.02em` letter spacing for page titles and focal totals only. Keep body
  text and labels at normal spacing. Do not shrink long names to force a fit.
- Keep units visible: `184.6 g`, `240 pts`. Format server-provided values;
  never calculate points or reinterpret domain rounding in the browser.

## 5. Spacing, shape, and layout

Use a 4px spacing scale: **4, 8, 12, 16, 24, 32, 48, 64px**. Express layout
spacing in rem in implementation, using a 16px root reference without preventing
user text resizing.

| Element | Default |
| --- | --- |
| Label to input | 8px |
| Input to helper/error text | 4px |
| Between form fields | 24px |
| Related controls | 8–12px |
| Panel padding | 16px mobile; 24px desktop |
| Major sections | 32px mobile; 48px desktop |
| Control/panel border | 2px solid ink |
| Internal table dividers | 1px solid ink |
| Corners | 4px controls and panels; 0 for table grids |
| Primary button shadow | `3px 3px 0 #000000` |
| Focal panel shadow | `4px 4px 0 #000000` |

Keep ordinary panels, fields, tables, and badges flat. A static focal panel can
have a hard shadow, but never gets hover movement or a pointer cursor. Leave
space for shadows and focus outlines so surrounding containers do not clip them.

### Responsive structure

| Width | Layout |
| --- | --- |
| Below 40rem | Single column, 16px page gutters; stack action groups when needed |
| 40rem to below 64rem | 24px gutters; use two columns only when content remains readable |
| 64rem and above | 32px gutters; admin may use a 15rem navigation column |

Center the overall shell within a maximum width of 80rem. Keep single-task forms
within 36rem. Align navigation, page titles, forms, and tables to a shared grid.
Content determines height; do not fix card heights or truncate critical text.

Reuse the guest, student, and admin shells from the Blade views guide. Keep
navigation visible with wrapping links on small screens; longer admin groups
may use native `details`/`summary`. Do not require a scripted hamburger menu.

Keep data tables semantic and place wide tables inside a labeled, keyboard
scrollable region. Contain horizontal scrolling there, never on the whole page.
Allow action columns to wrap. Keep pagination visible and usable on a phone.

### Student home composition

This is a hierarchy sketch, not an additional feature list. Braces represent
server-provided content; no example establishes a point value.

```text
Desktop
+----------------------------------------------------------+
| Smart E-Waste       Home  Rewards  History  Profile        |
|                                                          |
| Your points                                              |
| +----------------------------------+                     |
| | Available points                 |   [View QR code]     |
| | {balance} pts                    |   Browse rewards     |
| +----------------------------------+                     |
|   hard shadow; lavender fill                             |
|                                                          |
| Recent transactions                                      |
| Date              Weight         Points         Status   |
| -------------------------------------------------------- |
| {server-provided rows}                                   |
+----------------------------------------------------------+

Mobile reading order
Navigation -> Page title -> Points -> Actions -> History
```

Show the remaining documented navigation in the shared shell; this sketch only
illustrates the primary group. Keep the transaction area mostly white rather
than placing each row in a separate shadowed card.

## 6. Component rules

### Buttons and links

| Variant | Appearance | Use |
| --- | --- | --- |
| Primary | Lavender, ink text/border, 3px hard shadow | Main task action |
| Secondary | White, ink text/border, no shadow | Supporting action |
| Text link | Ink, underlined, no box | Navigation within content |
| Destructive | White, danger text/border, no shadow | Documented void/cancel or other destructive action |

Use at least 44px height and 16px horizontal padding for buttons. Let labels wrap
and controls grow rather than clip. Use anchors for navigation and buttons for
actions. Keep one primary action per form or coherent task group.

| State | Required treatment |
| --- | --- |
| Hover | Primary fill becomes lavender-strong; secondary becomes lavender-wash |
| Pressed | Enabled primary moves 2px down/right and shadow becomes 1px; immediate feedback |
| Keyboard focus | 3px ink outline with 3px offset on the light surfaces; no movement or delayed focus |
| Disabled | White fill, muted text, no shadow or movement; native disabled state and a nearby reason |
| Selected navigation | Lavender-wash, ink outline or side rule, stronger text, and `aria-current="page"` |

Gate hover effects with `(hover: hover) and (pointer: fine)`. Focus must remain
visible alongside hover, pressed, or selected styling. Avoid global opacity for
disabled controls because it also fades explanatory text. Do not simulate an
enabled action on an unavailable feature.

### Forms

- Put a persistent label above each input. State required or optional fields in
  text; placeholders are examples, never replacements for labels.
- Use white fields with 2px black borders, 4px corners, and a minimum height of
  44px. Keep input text at 1rem. Let text areas grow vertically.
- Prefer native inputs, selects, checkboxes, and file pickers. Retain recognizable
  controls and their focus behavior. Enlarge checkbox/radio hit areas using labels.
- Match the documented form rules. Explain formats and units before submission.
  Preserve safe old input after validation; never repopulate passwords or secrets.
- Show a concise error summary near the form heading and a specific message by
  each invalid field. Link summary items to fields and associate messages with
  `aria-describedby`; mark invalid fields with `aria-invalid="true"`.
- Use a danger border plus written error text. A red outline alone is insufficient.
- Keep consequential action explanations beside the existing form, with the
  affected record named. Do not add a modal, route, or new confirmation workflow
  solely to satisfy the visual style.

### Tables, rewards, and status

Use real table headings, a caption or accessible name, lavender-wash header
cells, and white body rows. Give cells 12–16px padding. Keep status text visible;
never make a row's meaning available only on hover. Lists remain paginated.

Use cards for rewards when they help compare an image, name, and point cost.
Keep them white and flat with consistent borders. Make the detail link explicit;
do not nest buttons inside a whole-card link. Use supplied images with meaningful
alternative text; omit decorative photography that does not help choose a reward.

Badges use 0.875rem text, 4px vertical and 8px horizontal padding, a 1px border,
4px corners, and no shadow. Pair semantic color with a readable state label.

Use simple inline SVG icons with a consistent 2px stroke at 20–24px. Reuse the
existing icon source before adding assets. Pair unfamiliar icons with text;
decorative icons are hidden from assistive technology. No new icon library is
required by this guide.

## 7. Apply the system to real pages

| Page group | Visual priority and guardrails |
| --- | --- |
| Login and password pages | One quiet form; lavender primary action. Preserve the API contract's account-safe error messaging. |
| Student home | Emphasize available points, then QR access, rewards, and recent transactions. Distinguish balance from leaderboard points earned. |
| Profile and QR | Give the QR image an unobstructed black-on-white area. Preserve the renderer's quiet zone; never recolor, round, crop, overlay, or decorate the code. Explain the documented effect of regenerating it beside that action. |
| Rewards and redemptions | Put point cost beside the reward name and action. Label pending, fulfilled, and cancelled records distinctly according to the contract. Show the server's reason when an action is unavailable. |
| Histories and leaderboards | Favor aligned rows, units, and pagination. Label the ranking metric explicitly; do not present spendable balance as points earned. |
| Admin dashboard | Prioritize bins and work requiring attention. Use a compact summary band for documented totals, then flat tables; no decorative charts. |
| Admin management | Use consistent list, detail, and form layouts. Surface the documented activation/status controls, not invented Delete actions. |
| Reports and session review | Style only agreed fields and actions. Report contents and unresolved review actions remain team decisions. |
| Notifications | Make unread/read distinguishable by label and weight, not just color. Use the existing form action for marking a notification read. |

For bin fill, a simple bordered meter may accompany the percentage and written
status. Its black fill edge/outline must remain clear on white. The server owns
thresholds and status; the UI does not infer either from the percentage.

Display `null` readings as **No reading**, never `0%` or a healthy state. On a
failed dashboard refresh, identify the displayed data as last known, retain its
last successful update time, and show **Could not refresh**. Do not replace
previous readings with zeros or claim the bin itself is offline solely because
the dashboard request failed.

Display times in the configured Asia/Manila timezone. Include a visible timezone
label where dates are compared, such as table headers or dashboard update text.
Only show deposit success and credited points when the server confirms them;
accepted or pending completion must not look completed.

## 8. Feedback, copy, and motion

Use short, plain sentences and specific verbs: **Save changes**, **View QR code**,
**Redeem reward**, **Mark as read**. Keep action names consistent across the button
and its result. Avoid vague **Submit**, **Oops**, or **Something went wrong** when
the interface can explain the problem.

| Situation | Treatment |
| --- | --- |
| Empty list | A short heading and context, such as "No transactions yet." Offer a next step only when it exists. |
| Form success | Persistent inline flash message after redirect; no auto-dismiss timer |
| Validation failure | Error summary plus inline errors, preserving safe input |
| Permission/server failure | A clear page heading and an available recovery link; do not show a success state |
| Normal navigation/submission | Native browser navigation; do not add a fake loader or assume success |
| Dashboard refresh | Preserve content dimensions and reading position; no pulsing numbers or re-entry animation |

Motion is optional. Default to instant changes for typing, keyboard navigation,
table updates, and repeated actions. Give pointer presses immediate feedback as
specified above; use no bounce, scale-from-zero, page entrance, or scroll reveal.

If a small existing interaction needs a transition, use 120–160ms and
`cubic-bezier(0.23, 1, 0.32, 1)`. Name the properties explicitly and limit motion
to transform/opacity; do not use `transition: all`. Change border, fill, and
shadow states instantly. Do not animate keyboard focus or delay interaction.

Under `prefers-reduced-motion: reduce`, remove transforms and transitions; keep
the immediate fill, border, and focus feedback. Do not add JavaScript or an
animation package for these effects.

## 9. Accessibility requirements

Target WCAG 2.2 AA. These rules are a working baseline, not proof that a complete
page conforms; verify the implemented experience.

- Normal text needs at least 4.5:1 contrast. Large text needs at least 3:1, though
  the approved text pairs above exceed 4.5:1. Check helper text and placeholders
  too. See [W3C text contrast guidance](https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html).
- Essential control boundaries, state indicators, and meaningful icons need
  3:1 against adjacent colors. Keep the black outline on lavender controls and
  meters. See [W3C non-text contrast guidance](https://www.w3.org/WAI/WCAG22/Understanding/non-text-contrast.html).
- Use 44×44px minimum targets for buttons, pagination, and navigation controls
  as this project's comfortable touch standard. Inline prose links are exempt
  from this project sizing rule. WCAG 2.2 AA uses a 24×24px minimum with defined
  exceptions; see [W3C target-size guidance](https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum.html).
- Support text resizing to 200% and a 320 CSS-pixel viewport without loss of
  content or page-wide horizontal scrolling. Wide data tables may scroll in
  their own regions. Check 400% browser zoom from a 1280px desktop viewport.
  See [W3C reflow guidance](https://www.w3.org/WAI/WCAG22/Understanding/reflow.html).
- Provide a skip link, meaningful landmarks, visible focus, and a logical tab
  order. Focus outlines and focused controls must not be clipped or obscured.
  Do not add positive `tabindex` values or make essential content hover-only.
- Use native elements and accessible names. Show meaningful text for statuses;
  color and icons are supporting signals. Test with keyboard and a screen reader.
- If the existing dashboard script updates feedback in place, use a small polite
  live region for refresh failures/recovery. Do not announce the entire dashboard
  on each poll or move the user's focus when readings update.
- Keep useful content visible with reduced motion and browser forced colors.
  Controls retain real borders and focus outlines even when shadows disappear.

## 10. Frontend implementation boundaries

- Implement with Blade and Tailwind, using ordinary `POST` forms with `@csrf`
  and the documented method override where needed. Success and validation
  feedback follow the existing redirect contract.
- Map these tokens into the existing Tailwind/CSS setup once and reuse shared
  Blade components. Keep one source for each token; do not scatter approximate
  purple values across templates. Use explicit component classes with shallow
  specificity rather than broad selectors that unexpectedly restyle other pages.
- Preserve the one permitted vanilla JavaScript script: the admin dashboard
  summary poll. Read `config('ewaste.dashboard_poll_seconds')`; the current
  documented/default interval is 10 seconds, with `[P34]` retaining its proposal
  status. Do not add polling elsewhere or change the interval for visual effect.
- Do not add AJAX CRUD, frontend frameworks, animation libraries, Bootstrap,
  chart libraries, custom selects, or scripted navigation to implement this style.
- Keep server-rendered data truthful. Do not fabricate totals, rates, successful
  outcomes, QR values, or empty-state readings to make a design look finished.
- Build assets with the documented Vite process on a development machine, not
  the Raspberry Pi. Do not require third-party font or icon requests for rendering.

## 11. Design handoff and review

Designers should provide the relevant mobile and desktop screens, reusable
components named with these tokens, and applicable empty/error/disabled/focus
states. Include long names, long labels, and unknown readings in review examples.
Mark mock data as illustrative and link each behavior to its project doc section.

Developers should compare browser renders with those designs before handoff.
Review at 320px, 390px, 768px, and 1280px widths, plus zoom and keyboard checks.
Use the repository's applicable formatting and verification commands for the
implementation; a design file alone cannot validate a rendered page.

- [ ] White dominates; lavender has a clear purpose; the main task reads first.
- [ ] Colors, type scale, spacing, borders, corners, and shadows match this guide.
- [ ] Ordinary tables and forms remain quiet; static content does not look clickable.
- [ ] Buttons, links, labels, errors, and unavailable actions are understandable.
- [ ] Contrast, focus, touch targets, reduced motion, zoom, and table scrolling work.
- [ ] Data states, points wording, units, times, and actions follow the source docs.
- [ ] QR readability is checked with the intended scanner when that page is built.
- [ ] The implementation stays within Blade, Tailwind, and the allowed polling script.
- [ ] No new feature, dependency, business rule, or unresolved team decision is hidden
  inside a visual change.
