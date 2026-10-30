# IoT-Based Smart E-Waste Collection and Monitoring System

A smart e-waste bin built around an ESP32-S3 controller and a Raspberry Pi 4,
with a Laravel web application for
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

The **web app is only a server**. The Pi runs it alongside a separate program,
the **bin station**, which handles the QR scanner, the camera and the
touchscreen. Every part of the bin sits behind the ESP32-S3 controller, either
wired to it or reached through the bin station, and only the controller talks to
the web app, through one small signed API.

That is what lets the web app stay on the Pi or move to the school's own
platform without a rewrite. The controller runs the deposit sequence and owns
all safety logic, so a slow or crashed Pi can never leave the actuator running.
The project builds one bin.

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
  controller/           ESP32-S3 — runs the deposit sequence, talks to the server
  station/              the bin station, Python on the Pi: QR scanner, camera
                        check and the bin's screen (not started yet)

tools/simulator/      signs requests and drives the full deposit flow in Python
scripts/              the two portability checks from System_Plan.md §8.1
.github/workflows/    CI: the AGENTS.md §8 checks, required to merge into main
sessions/             AI agent session recaps (working notes, not design docs)
SETUP.md              how to build and run all of it
```

---

## How a deposit works

```
scan QR ──► server checks the student, the bin, the rule and the daily cap
                                │
                         weigh the item
                                │
            the Pi's camera confirms something is there
                                │
              server validates the weight and computes the points
                                │
                 actuator pushes the item into the bin
                                │
  actuator current shows the end of its stroke ──► transaction + points
                                                   committed together
```

Four rules hold the whole thing together:

1. **The server decides everything.** The controller reports grams and a
   pass/fail. It never computes points and never decides whether a bin is full.
2. **Credit comes last** — after verification passes *and* the actuator
   confirms, in one database transaction with the transaction record.
3. **The ledger is append-only.** There is no balance column anywhere: a balance
   is `SUM(points_delta)`, and a correction is a new reversal row.
4. **Fail closed.** If the server or the bin station is unreachable the bin
   refuses and nothing moves. Once an item is physically in the bin, the
   firmware retries, then saves the request and resends it — including after a
   reboot.

---

## Tech stack

| Layer | Choice |
|---|---|
| Host | Raspberry Pi 4, Raspberry Pi OS Lite 64-bit, booted from USB SSD |
| Server | Nginx + PHP 8.4-FPM, native via `apt`; no Docker |
| App | Laravel 13, Blade, Tailwind (built with Vite on a dev machine) |
| Data | MariaDB 11.8 LTS, InnoDB, `utf8mb4`, every timestamp in UTC |
| Firmware | Arduino framework / C++ on the ESP32-S3, built with PlatformIO |
| Bin station | Python 3 with `picamera2`, `numpy` and `pyserial` from `apt`; Chromium in kiosk mode for the touchscreen |
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

The web app's deposit path, points, redemptions and reporting work and are
tested. Accounts (signing in) and the two programs in `firmware/` are not built
yet, so it is not a working system end to end.

**Build tracks**

Each track has a build spec: the steps, the service methods and the tests each
step needs.

| Track | Covers | Spec | State |
|---|---|---|---|
| A | Accounts: registration, sign-in, passwords, users, organizations | [web/docs/TRACK-A-ACCOUNTS.md](web/docs/TRACK-A-ACCOUNTS.md) | Only step A0 built |
| B | Device API, bins, points rule, ledger credit, deposit sessions | [web/docs/TRACK-B-DEPOSIT-PATH.md](web/docs/TRACK-B-DEPOSIT-PATH.md) | Built (#30) |
| C | Rewards, redeeming, fulfilling, cancelling, voids | [web/docs/TRACK-C-REDEMPTIONS-AND-VOIDS.md](web/docs/TRACK-C-REDEMPTIONS-AND-VOIDS.md) | Built (#31) |
| D | Home, history, leaderboards, dashboard, notifications | [web/docs/TRACK-D-REPORTING-AND-HISTORY.md](web/docs/TRACK-D-REPORTING-AND-HISTORY.md) | Built (#32) |
| E | The bin station | [firmware/docs/TRACK-E-BIN-STATION.md](firmware/docs/TRACK-E-BIN-STATION.md) | Not started |

**Real and usable now**

- Migrations for the 12 application tables plus Laravel's
  `password_reset_tokens` and `sessions`, with every key, unique constraint and
  index from `Database_Schema.md`. Laravel's stock `cache` and `jobs` migrations
  sit beside them.
- Eloquent models with their casts, relations and the helpers the rules need
  (`RewardRule::pointsFor()`, `SessionStatus`, `LedgerEntryType`).
- Every route from `API_Design.md` §5 and §7: 7 device endpoints and the full
  student and admin route list, named and with middleware applied.
- The device error contract: `DeviceErrorCode` (status and `retryable` per code),
  `DeviceApiException`, and `DeviceExceptions` so no device route can ever
  answer with HTML.
- The HMAC signature middleware, tested against both signing vectors and the
  failure cases in `API_Design.md` §8.
- The seven device endpoints and the deposit state machine: telemetry with
  `panel_open` and `fault_code`, bin config, sessions with row locks and replay
  handling, and the points credit in the same transaction (Track B).
- The ledger's other side: the reward catalogue, redeeming, fulfilling and
  cancelling redemptions, and voiding a transaction, all through
  `PointsService` (Track C).
- The read side: the student's home page and history, both leaderboards, the
  admin dashboard with its live summary, the review list and notifications
  (Track D).
- The bin and points-rate admin pages.
- Feature tests for all of the above: `cd web && php artisan test`.
- FormRequest validation rules wherever `API_Design.md` tabulates them,
  including the three constraints between the reward rule variables.
- `config/ewaste.php` and `.env.example`, fully annotated.
- The device simulator: working, dependency-free, with the seven integration
  scenarios from `API_Design.md` §8.
- The two swappability checks, verified to catch real violations.

**Not built yet**

- **Accounts (Track A).** Login, logout, registration and email confirmation,
  password links, the profile and QR page, the admin user and organization
  pages, and `FirstAdminSeeder`. Until they land nobody can sign in, so the
  pages above are reached only by the tests, which sign in a factory user.
- **The reports page**, until the team decides what reports contain
  (`API_Design.md` §10 q2).
- **The visual design.** The views that exist are bare Blade with no styling.
- **The bin station (Track E)**, in `firmware/station/`.

Unbuilt controllers and services are documented skeletons: each carries the
rules it must follow and the doc section they come from, and raises
`LogicException` so an unbuilt path fails loudly.

**Not yet matching the design**

- `firmware/` still holds a NodeMCU controller and the `camera/` and `display/`
  programs. It needs reworking for the ESP32-S3: the pin map, the `station` and
  `interlock` modules and the actuator's current sense (`System_Plan.md` §2 and
  §8.2). `camera/` and `display/` are not part of the design; the bin station
  takes over their jobs.
- Some choices made while building Tracks C and D are not in `docs/` yet: a
  required `cancel_reason` when cancelling a redemption, 404 for an inactive
  reward's page, the `home_recent_transactions` setting, and leaderboard ties
  ordered by user id.
- `firmware/AGENTS.md` still says only `Api` knows about HTTP, though the
  controller's `station` module also speaks HTTP to the bin station. Changing it
  needs the whole team.

**Decisions still open**

Carried over from the docs, each marked `TODO(team)` where it matters in the
code:

- The five point values, all needing instructor approval: `points_per_gram`,
  `minimum_weight_g`, `maximum_weight_g`, `daily_points_cap`, and each reward's
  `points_cost` (`System_Plan.md` §5.7).
- The hardware weight limit — `config/ewaste.php` has no value for it yet
  (`API_Design.md` P37).
- Hardware items for the hardware team: the actuator current-sense wiring and
  the service-panel interlock pin, and the SSD with its adapter, on the
  schematic; the ESP32-S3 pin map verified on the bench (`System_Plan.md` §10).
- Bench values: the actuator's current thresholds, stroke timeout and rest time,
  and the camera's lens position and pass thresholds (`System_Plan.md` §10).
- The bin station API and the new telemetry fields, proposed as P42–P45 in
  `API_Design.md` §9.
- The eight open questions in `API_Design.md` §10 — what an admin can do with a
  session in review, what the reports contain, whether users can change their
  own password, the set-password link lifetime, the firmware timings, bulk
  invitations, the hardware weight limit, and which QR package to use.

---

## License

MIT License — see [LICENSE](LICENSE).
