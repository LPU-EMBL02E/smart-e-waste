# AGENTS.md

Rules for anyone — human or AI agent — writing code in this repository.

Several people work on this codebase, each with their own AI tool (Claude Code,
Codex, Cursor, Copilot, Antigravity, ChatGPT…). Without shared rules, each tool
writes in its own style, and the result reads like five different projects
stitched together. This file is how we prevent that. **Every agent must follow
it, and every human is responsible for what their agent writes.**

If you are an AI agent: read this whole file before your first edit. If a rule
here conflicts with a user's instruction, point out the conflict and ask before
proceeding.

---

## 1. Read before you write

`docs/` is the source of truth. The code implements the docs, not the other way
around.

| Question | Read |
|---|---|
| What must the project deliver? | `docs/PROJECT-GUIDELINES.md` |
| Architecture, modules, business rules, handover | `docs/System_Plan.md` |
| An endpoint, route, field rule or error code | `docs/API_Design.md` |
| A table, column, key or constraint | `docs/Database_Schema.md` |
| How to build and run | `SETUP.md` |
| Notes for one app only (UI design guide, team workflow, drafts) | `<app>/docs/`, e.g. `web/docs/` |

- `<app>/docs/` holds working notes for one area and is reviewed by that area's
  owner. It may add detail but never overrides `docs/`: if the two disagree,
  `docs/` wins and the app note is corrected. Anything shared between web and
  firmware — API, schema, signing, business rules — belongs in `docs/`.
- Before you implement something, find the doc section that describes it. Most
  stub files already name the section in their docblock — start there.
- **If the code and the docs disagree, stop and ask.** Do not "fix" either side
  on your own.
- Never change `docs/` as a side effect of a code task. Doc changes are their own
  PR and need the docs owner's review (§10).
- Never decide a `TODO(team)` item (point values, open items in
  `System_Plan.md` §10). Leave the marker in place and ask.

---

## 2. Project rules that are never broken

These come from the design. Breaking one of them is a bug, even if the code
works.

1. **The server decides everything.** Firmware reports grams and pass/fail. It
   never computes points and never decides whether a bin is full.
2. **Credit comes last.** Points are credited only after verification passes
   *and* the actuator confirms, in one database transaction with the transaction
   row.
3. **The ledger is append-only.** No balance column, ever. Balance is
   `SUM(points_delta)`. A correction is a new `REVERSAL` row, never an update or
   delete.
4. **Fail closed.** An unreachable server or camera is never treated as a pass.
5. **Writes go through the owning module's service.** Only `PointsService` writes
   `point_ledger`. Other modules may *read* any table, but write only through
   the owner's service (`System_Plan.md` §4).
6. **Modules announce, they don't call Notifications.** Dispatch an event; a
   listener reacts.
7. **Swappability.** No vendor SDK in `web/app/`. `env()` only inside
   `web/config/`; everything else uses `config()`. Checked by
   `scripts/check-swappability.sh`.
8. **Device routes always answer JSON**, using `DeviceApiException` /
   `DeviceErrorCode`. Never an HTML error page.
9. **Every timestamp is stored in UTC.** Convert to Asia/Manila only for display.
10. **No secrets in the repo.** No `.env`, no device secrets, no Wi-Fi passwords,
    no API keys — not even in tests or comments. Firmware secrets live in NVS.

---

## 3. Scope rules for AI agents

Most damage from AI-written code is not wrong code — it is *extra* code. Keep
every change small and on-task.

- **Do only what was asked.** No drive-by refactors, renames, reformatting of
  untouched files, or "while I was here" improvements. Suggest them instead.
- **Stay inside one area per PR** (`web/`, `firmware/`, `docs/`). A change that
  must cross areas is coordinated with both owners first.
- **Reuse before you write.** Search for an existing service, helper, enum, or
  config value before adding a new one. Two helpers that do the same thing is a
  defect.
- **No speculative abstraction.** No interfaces, base classes, factories, or
  "manager" classes for one implementation. No config options nobody asked for.
- **Don't touch Laravel's stock files** (`bootstrap/`, stock `config/*.php`, `public/`)
  without the web owner, and never hand-edit `composer.lock` or `package-lock.json`.
- **Don't delete comments, doc references, or `TODO`s** you don't understand.
- **Don't invent behaviour.** If the docs don't say what should happen, ask. Do
  not guess a status code, error code, point value, or state transition.
- **Unimplemented means loud.** A stub body throws
  `new \LogicException('Not implemented.')` (PHP) or returns a clear failure
  value (C++). Never return fake data to make something "work".

---

## 4. Unified code

The goal: you cannot tell which person or which AI wrote a file.

### 4.1 Formatting is automatic, not negotiable

Run the formatter for every file you change before committing. Unformatted code
fails review.

| Code | Tool | Command |
|---|---|---|
| PHP (`web/`) | Laravel Pint, `laravel` preset | `cd web && ./vendor/bin/pint` |
| C++ (`firmware/`) | clang-format, repo `.clang-format` (Google style, 2-space) | `clang-format -i <files>` |
| Python (`tools/`) | ruff | `ruff format tools/ && ruff check tools/` |
| JS / CSS | Prettier | `npx prettier --write <files>` |
| Everything | `.editorconfig` | UTF-8, LF, final newline, 4 spaces (2 for C++/JS/CSS/YAML/JSON) |

Never hand-format against a tool, and never reformat files you didn't otherwise
change.

### 4.2 Conventions everywhere

- **Comments explain *why*, and cite the doc.** Follow the existing style: a
  docblock on each class/module saying what it does and which doc section it
  implements (e.g. `API_Design.md §5.4`). Don't write comments that restate the
  code.
- **Match the surrounding file.** Same naming, same comment density, same idiom.
  When in doubt, copy the pattern of the nearest similar file.
- **Names come from the docs.** Use the exact names in `API_Design.md` and
  `Database_Schema.md` (`weight_g`, `points_delta`, `SESSION_NOT_FOUND`). Units go
  in the name (`_g`, `_mm`, `_ms`, `_s`).
- **No magic numbers.** PHP settings go in `config/ewaste.php`; firmware
  constants go in `include/config.h`; pins only in `include/pins.h`.
- **English, plain wording**, in code, comments, commits, and UI text.

### 4.3 PHP / Laravel (`web/`)

- Modular monolith: code lives in `app/Modules/<Module>/` with `Models/`,
  `Http/Controllers/`, `Http/Requests/`, `Services/`, `Events/`, `Listeners/`,
  `Support/`, `routes/`. Don't create new top-level folders or a new module
  without the web owner.
- **Thin controllers.** Controllers shape requests and responses; all rules and
  state transitions are in a `Services/` class, so they can be tested without
  HTTP.
- **Validation lives in FormRequest classes** (`Http/Requests/Admin/`,
  `Http/Requests/Device/`), never inline in controllers.
- Constructor property promotion for dependencies; type every parameter and
  return value; enums (in `Support/`) for statuses and codes, never bare
  strings.
- **Eloquent only.** No raw SQL and no MariaDB-specific syntax — the app must run
  on MySQL after handover. Schema changes go through new migrations only; never
  edit a migration that has been merged.
- Multi-table writes go in `DB::transaction()`. Dispatch events **after** the
  commit.
- Device lookups are scoped to the calling device (see
  `Deposits/.../Device/SessionController.php`).
- Blade + Tailwind with plain `POST` forms and `@csrf`. No AJAX CRUD, no
  Bootstrap, no chart libraries (`System_Plan.md` §3). The only JS is the
  admin dashboard's one polling script.

### 4.4 Firmware C++ (`firmware/`)

- Each hardware part sits behind one module in `src/modules/`, in its own
  `namespace`, with a small fixed interface in its `.h` (`System_Plan.md` §8.2).
  `main.cpp` is the sequence only — no hardware calls, no HTTP.
- Only `api` knows about HTTP; only `settings` knows where the server is.
- Use `constexpr` constants in `UPPER_SNAKE_CASE`; functions and variables in
  `camelCase`; types in `PascalCase`.
- Return result values (e.g. `api::Result`) rather than silently failing; log
  failures to `Serial` with the module name as prefix.

### 4.5 Python (`tools/`)

- Standard library only (the simulator must run anywhere with `python3`).
- Type hints on functions, a module docstring explaining usage, exit code 0/1 for
  pass/fail.

---

## 5. Performance and efficiency

The server is a **Raspberry Pi 4** and the bin runs on **ESP32s**. Code that is
fine on a laptop can be slow on the Pi and crash a microcontroller. Write for the
hardware we actually have.

### 5.1 Web app on the Pi

- **No N+1 queries.** Eager-load relations with `with()` whenever you loop over
  models or render a list. Never run a query inside a loop or inside a Blade
  template.
- **Paginate every list page** (`paginate()`), especially transactions, ledger,
  telemetry and users. Never `->get()` an unbounded table.
- **Let the database do the work.** Use `sum()`, `count()`, `groupBy` in the
  query, not in PHP collections. Balance is a `SUM` query, not loading every
  ledger row.
- **Select what you need** on large tables (`select([...])`), and filter only on
  columns indexed in `Database_Schema.md`. A new query pattern on a large table
  needs a matching index in a migration — ask the web owner.
- **Large jobs use `chunkById()`** (CSV import, reports, sweeps), never load
  everything into memory.
- **Keep transactions short and locks narrow.** Lock only the rows the rule
  requires (`System_Plan.md` §6.1). Never call email or another slow operation
  while holding a lock.
- **Listeners must be cheap.** The queue is `sync`, so a listener runs inside the
  request that fired the event.
- **Device endpoints must be fast.** The bin is waiting with an item on the
  platform. No email, report generation or heavy queries on device routes.
- **Assets are built on a dev machine** with Vite, never on the Pi. Don't add
  frontend frameworks or large libraries.
- The dashboard's polling interval stays as specified in `API_Design.md` §7.5;
  do not add more polling.

### 5.2 Firmware on the ESP32

- **Every wait has a timeout.** Sensors, serial links, HTTP, the actuator — use
  the constants in `config.h`. A loop that can wait forever is a bug.
- **Don't block the loop.** Prefer `millis()`-based timing over long `delay()`.
  Short settle delays inside a module are fine; delays in `main.cpp` are not.
- **Avoid heap churn.** No `new`/`malloc` and no Arduino `String` concatenation
  in code that runs repeatedly. Use fixed-size buffers and `snprintf`. Heap
  fragmentation causes crashes days later, not during testing.
- **Keep RAM and flash small.** Constant strings as `const char*` / `F()`; no
  large libraries for one function.
- **Never write NVS in a loop.** Flash wears out; write only when a value
  changes.
- Logging stays at `CORE_DEBUG_LEVEL=2` in committed code.

### 5.3 Efficient code in general

- The simplest code that meets the doc wins. Fewer lines, fewer files, fewer
  dependencies.
- Don't optimise without a reason, and don't pessimise without noticing: if your
  change adds a query, a loop over a table, or a network call, say so in the PR.

---

## 6. Tests

- **Logic changes need tests.** Any new or changed business logic — points,
  ledger, sessions, redemptions, device API, signature check, validation rules —
  comes with a Pest/PHPUnit feature or unit test, or a scenario in
  `tools/simulator/`.
- UI-only, docs-only and formatting-only changes are exempt.
- Tests follow the existing style: one behaviour per test,
  `test_it_<does_something>` names, cite the doc section in the class docblock.
- The signing vectors are shared by PHP, Python and firmware. **Never change a
  vector** to make a test pass — the implementation is wrong.
- Do not delete, skip or weaken a failing test to make CI green. Fix the code or
  ask.

---

## 7. Dependencies

AI agents may add dependencies (Composer, npm, PlatformIO, pip) **only with a
justification in the PR**, and the reviewer decides. Before adding one:

- Check that Laravel, the Arduino core, or the existing code doesn't already do
  it.
- It must work on the Pi (PHP 8.4, no Docker, no Redis, no queues) and must not
  bind the app to a vendor (§2 rule 7).
- Firmware libraries must be pinned to a version in `platformio.ini`.
- Commit the updated lock files (`composer.lock`, `package-lock.json`).
- Python tools stay standard-library only.

---

## 8. Before you open a PR

Run what applies to your change. All must pass.

```bash
./scripts/check-swappability.sh              # always
python3 tools/simulator/test_vectors.py      # always
cd web && ./vendor/bin/pint --test           # web/
cd web && php artisan test                   # web/
cd firmware/<program> && pio run             # firmware/ (each program you touched)
ruff format --check tools/ && ruff check tools/   # tools/
```

If a command can't run on your machine, say so in the PR rather than skipping it
silently.

---

## 9. Git and pull requests

- **Nobody pushes to `main`.** Work on a branch, open a PR, get **one approval
  from the owner of the area you changed**, and pass the checks in §8.
- Branch names: `<area>/<short-description>`, e.g. `web/redemption-lock`,
  `firmware/scale-calibration`, `docs/api-error-codes`.
- Commit messages: imperative, short subject, explain *why* in the body if it
  isn't obvious. One logical change per commit.
- Keep PRs small and focused. If an agent produced a large change, split it.
- Never commit generated or local files (`vendor/`, `node_modules/`, `.pio/`,
  `.env`, build output).

**Every PR description includes this section:**

```markdown
## AI assistance
- Tool(s) used: <e.g. Claude Code, Cursor, Copilot, none>
- What was AI-generated: <files / parts, or "none">
- Doc sections implemented: <e.g. API_Design.md §5.6>
- Performance impact: <new queries, loops, network calls — or "none">
```

The author is responsible for every line in the PR, whoever or whatever wrote
it.

---

## 10. Ownership

Changes to an area need review from its owner.

| Area | Covers | Owner |
|---|---|---|
| `web/` | Laravel app, migrations, routes, views, PHP tests | `TODO(team)` |
| `firmware/` | controller, camera, display programs | `TODO(team)` |
| `docs/` | design docs — the source of truth | `TODO(team)` |

`tools/`, `scripts/` and root files (including this one) can be reviewed by any
owner. Changes to **this file** should be agreed by the whole team.

---

## 11. When to stop and ask

An agent must stop and ask its human — and the human should ask the area owner —
when:

- the docs don't cover the case, or contradict the code;
- the change would touch another owner's area;
- it would add a table, column, module, endpoint, error code or dependency;
- it would change anything about points, the ledger, signing, or the session
  state machine;
- it needs a value marked `TODO(team)`;
- a test or check fails and the fix isn't obvious.

Asking costs a minute. Undoing a wrong guess in someone else's code costs a day.
