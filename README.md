# IoT-Based Smart E-Waste Collection and Monitoring System

A smart e-waste bin built around an ESP32, with a Laravel web application for
students — who earn points for deposits and redeem them for rewards — and for
admins, who monitor bins and manage users, transactions and rewards.

A student scans their QR code, places an item on the weighing platform, and the
bin measures it, confirms with a camera that something is actually there,
transfers it into the collection container, and records the deposit. Points are
credited only after both the verification and the actuator confirm — never
because the load cell felt weight.

**EMBL02E project.** The bin and the web app are handed over to the school at
the end, so portability is a design goal rather than an afterthought.

---

## The one design rule

The Raspberry Pi is **only a server**. Everything at the bin runs on the bin's
own ESP32 boards, and only the controller talks to the server, through one small
signed API.

That is what lets the app stay on the Pi or move to the school's own platform
without a rewrite, and what lets one server run more than one bin.

---

## Repository layout

```
docs/                 the design. The source of truth — read this first
  PROJECT-GUIDELINES.md   what the project must deliver, and the rubric
  System_Plan.md          architecture, tech stack, business rules, handover
  API_Design.md           every endpoint, route, field rule and error code
  Database_Schema.md      all 12 tables, keys and constraints
  Database_ERD.mmd        the same, as a diagram

web/                  the Laravel app (modular monolith, 5 modules + 1)
  docs/                 web-only notes: UI design guide, account management,
                        team workflow, wireframes. docs/ above still wins
  app/Modules/          Identity, Devices, Deposits, Rewards, Reporting,
                        Notifications
  app/Support/Device/   the device API error contract
  config/ewaste.php     settings that are not data
  database/migrations/  the schema, defined in code
  routes/console.php    the one scheduled task

firmware/
  controller/           NodeMCU — runs the deposit sequence, talks to the server
  camera/               ESP32-CAM — "is an item present?"
  display/              ESP32-S3 panel — draws the screens with LVGL

tools/simulator/      signs requests and drives the full deposit flow in Python
scripts/              the two portability checks from System_Plan.md §8.1
SETUP.md              how to build and run all of it
```

---

## How a deposit works

```
scan QR ──► server checks the student, the bin, the rule and the daily cap
                                │
                         weigh the item
                                │
                    camera confirms something is there
                                │
              server validates the weight and computes the points
                                │
                 actuator pushes the item into the bin
                                │
      limit switches confirm ──► transaction + points committed together
```

Four rules hold the whole thing together:

1. **The server decides everything.** The device reports grams and a pass/fail.
   It never computes points and never decides whether a bin is full.
2. **Credit comes last** — after verification passes *and* the actuator
   confirms, in one database transaction with the transaction record.
3. **The ledger is append-only.** There is no balance column anywhere: a balance
   is `SUM(points_delta)`, and a correction is a new reversal row.
4. **Fail closed.** If the server is unreachable at the QR step the bin refuses
   and nothing moves. Once an item is physically in the bin, the firmware
   retries, then saves the request and resends it — including after a reboot.

---

## Tech stack

| Layer | Choice |
|---|---|
| Host | Raspberry Pi 4, Raspberry Pi OS Lite 64-bit, booted from USB SSD |
| Server | Nginx + PHP 8.4-FPM, native via `apt`; no Docker |
| App | Laravel 13, Blade, Tailwind (built with Vite on a dev machine) |
| Data | MariaDB 11.8 LTS, InnoDB, `utf8mb4`, every timestamp in UTC |
| Firmware | Arduino framework / C++, built with PlatformIO; LVGL on the panel |
| Device API | 7 JSON endpoints, HMAC-SHA256 signed, over the LAN |
| Ingress | Cloudflare Tunnel until handover; swappable by design |

Versions follow what Raspberry Pi OS itself ships and patches, so the Pi stays
maintainable with plain `apt upgrade` after handover
(`docs/System_Plan.md` §3).

---

## Getting started

```bash
# 1. the signing contract, no dependencies and no server needed
python3 tools/simulator/test_vectors.py

# 2. the two portability checks
./scripts/check-swappability.sh

# 3. build the app
#    (needs PHP 8.4 + Composer — see SETUP.md)
```

Full instructions: **[SETUP.md](SETUP.md)**.

---

## State of the repository

This is boilerplate and structure, not a working system.

**Real and usable now**

- All 14 migrations — the 12 application tables plus Laravel's
  `password_reset_tokens` and `sessions` — with every key, unique constraint and
  index from `Database_Schema.md`.
- Eloquent models with their casts, relations and the helpers the rules need
  (`RewardRule::pointsFor()`, `SessionStatus`, `LedgerEntryType`).
- Every route from `API_Design.md` §5 and §7: 7 device endpoints and the full
  student and admin route list, named and with middleware applied.
- The device error contract: `DeviceErrorCode` (status and `retryable` per code),
  `DeviceApiException`, and `DeviceExceptions` so no device route can ever
  answer with HTML.
- The HMAC signature middleware, written from the spec — **not yet executed**,
  because the authoring environment had no PHP. Its canonical string was
  verified against both test vectors by independent implementations in Python.
- FormRequest validation rules wherever `API_Design.md` tabulates them,
  including the three constraints between the reward rule variables.
- `config/ewaste.php` and `.env.example`, fully annotated.
- The device simulator: working, dependency-free, with the seven integration
  scenarios from `API_Design.md` §8.
- The two swappability checks, verified to catch real violations.
- Firmware: `platformio.ini` per program, the complete NodeMCU pin map, and the
  module interfaces from `System_Plan.md` §8.2.

**Skeletons with documented bodies**

Controllers, services, listeners, the seeder and the firmware module
implementations. Each one carries the rules it must follow and the doc section
they come from; the bodies raise `LogicException` so an unimplemented path fails
loudly instead of silently returning nothing.

Blade views are not written. `web/resources/views/README.md` lists the expected
view names per module.

**Decisions still open**

Carried over from the docs, each marked `TODO(team)` where it matters in the
code:

- The five point values, all needing instructor approval: `points_per_gram`,
  `minimum_weight_g`, `maximum_weight_g`, `daily_points_cap`, and each reward's
  `points_cost` (`System_Plan.md` §5.7).
- The hardware weight limit — `config/ewaste.php` has no value for it yet
  (`API_Design.md` P37).
- The actuator model, checked against the driver's stall current, and the
  NodeMCU pin map verified on the bench (`System_Plan.md` §10).
- The display panel's exact model, which the LVGL and serial configuration
  depend on.
- The eight open questions in `API_Design.md` §10 — what an admin can do with a
  session in review, what the reports contain, whether users can change their
  own password, the set-password link lifetime, the telemetry interval, bulk
  invitations, the hardware weight limit, and which QR package to use.
- The serial line protocols between the three boards are **proposed** in
  `firmware/README.md`, not specified in the docs. Confirm them before building
  both sides.

**One inconsistency in the docs**, left as-is rather than edited: both
`System_Plan.md` §6 and `API_Design.md` §1 refer to
`EMBL02E_Smart_EWaste_ERD_v4.mmd`; the file is `docs/Database_ERD.mmd`.

---

## License

MIT License — see [LICENSE](LICENSE).
