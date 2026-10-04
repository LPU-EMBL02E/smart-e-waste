# IoT-Based Smart E-Waste Collection and Monitoring System
### EMBL02E Project Plan — Tech Stack, API, and Deployment

---

## 1. Overview

A smart e-waste bin built around an ESP32, with a PHP/Laravel web application for
students (earn points for deposits, redeem rewards) and admins (monitor bins,
manage users, transactions and rewards). The finished bin and web app will be
physically handed over to the school after the project, so portability is a design
goal, not an afterthought.

**The one design rule:** the Raspberry Pi is only a server. Everything at the bin
runs on the bin's own ESP32 boards, and only the controller talks to the server,
through one small signed API. This is what
lets the app stay on the Pi or move to the school's own platform without a rewrite,
and what lets one server run more than one bin.

---

## 2. Hardware (Planned)

| Subsystem | Component(s) |
|---|---|
| Controller | ESP32 NodeMCU Development Board |
| Server | Raspberry Pi 4, booted from a USB SSD |
| Vision Subsystem | ESP32-CAM. Frame differencing against the empty platform in an LED-lit chamber; reports pass/fail and a score to the NodeMCU |
| User Identification | QR Code Scanner Module |
| Sensors & Measurement | Load Cell, Weight Amplifier Module, Ultrasonic Sensor |
| Automated Transfer | DC motor or linear actuator (model to be chosen), motor driver sized to its stall current, two limit switches |
| User Interface & Feedback | 7" capacitive touch display on an ESP32-S3 panel board (for example the Waveshare ESP32-S3-Touch-LCD-7, 800×480), with screens built in LVGL; Active Buzzer, LEDs |
| Power Supply & Protection | 12V 5A DC supply, a step-down converter for the Pi (5V 3A), a second 5V converter for the microcontroller boards and the display, fuse, flyback diodes |
| Network | Dedicated Wi-Fi router for the bin and the Pi |

**Notes:**
- The Raspberry Pi 4 is the **server** (Laravel + MariaDB). It drives no sensors, no
  camera and no display.
- The motor driver in the original parts list, the TB6612FNG, handles about 1.2 A
  continuous per channel. Check the chosen actuator's stall current against that
  before committing to it.
- The two limit switches are what make `actuator_ok` true or false. Without them
  the API's actuator confirmation means nothing.
- The 7" capacitive display is an ESP32-S3 panel board. It only runs the
  screen: the NodeMCU tells it what to show over a serial link. A NodeMCU can't
  drive a 7" panel itself, and the Pi is not used for it, so the Pi stays a pure
  server.
- There are three microcontroller boards, with one in charge. The NodeMCU runs the
  deposit sequence, is the only board that talks to the server, and is the only
  one that joins the Wi-Fi network. The ESP32-CAM and the panel board each do one
  job for it.
- The panel board takes 5 V over USB-C. Size the second 5 V converter for the
  NodeMCU, the ESP32-CAM and the panel together.

**Pin map for the NodeMCU.** The display uses none of these pins, and
the camera uses two. Seventeen pins are used, which fits with little to spare.

| Signal | GPIO | Notes |
|---|---|---|
| Load cell amplifier, data | 34 | Input-only pin. Power the amplifier from 3.3 V so this line stays at 3.3 V |
| Load cell amplifier, clock | 25 | |
| Ultrasonic trigger | 26 | |
| Ultrasonic echo | 35 | Input-only pin. Needs a voltage divider if the sensor runs on 5 V |
| Motor driver, PWM | 27 | |
| Motor driver, direction 1 | 32 | |
| Motor driver, direction 2 | 33 | |
| Limit switch, home | 13 | Internal pull-up |
| Limit switch, end | 22 | Internal pull-up |
| Panel board serial, receive / transmit | 16 / 17 | Hardware serial port 2 |
| ESP32-CAM serial, receive / transmit | 18 / 19 | Hardware serial port 1 |
| QR scanner data | 4 | Software serial, receive only, 9600 baud |
| Green LED | 21 | |
| Red LED | 14 | |
| Buzzer | 23 | |

Spare: GPIO 36 and 39, which are input only. GPIO 0, 2, 5, 12 and 15 affect how
the board boots, and GPIO 1 and 3 are the USB serial port, so leave those
unconnected.

**Serial ports.** Three devices need a serial link, and the NodeMCU has two spare
hardware serial ports. The QR scanner only sends, and slowly, so it goes on a
software serial pin. The camera and the panel board get the hardware ports.

The map is a starting point. Verify it on the bench, and revisit the motor pins
once the driver is chosen.

**Alternative not chosen:** an ESP32-P4 panel board could do the display, camera,
sensors and actuator on one board. It was passed over because it needs ESP-IDF
where the rest of the firmware is Arduino.

No fabrication begins before Checkpoint 1 approval.

---

## 3. Tech Stack by Layer

| Layer | Component | Role |
|---|---|---|
| **Host** | Raspberry Pi 4, Raspberry Pi OS Lite 64-bit (Trixie, Debian 13) | Physical self-hosted server |
| **Firmware** | Arduino framework, C++ | Controller program on the NodeMCU and camera program on the ESP32-CAM (Section 8.2) |
| | LVGL | The bin's touchscreen, on the ESP32-S3 panel board |
| **Presentation** | Blade | Dashboard pages; plain forms (`method="POST"` + `@csrf`) for CRUD, no AJAX |
| | Tailwind CSS (compiled via Vite on a dev machine, not the Pi) | Styling |
| | One small polling script (vanilla JS) | Admin live dashboard summary only |
| **Application** | Laravel 13 routes + controllers | Device API at `/api/v1/device`; standard web routes for students and admins |
| | `Http/Requests/` validation classes | Input validation per module |
| | Middleware: auth, role checks, CSRF, device signature check | Session-based for the web app, HMAC-based for the device API |
| | Laravel Mail | Account emails; the delivery service is a `.env` setting (Section 7.2) |
| **Services** | Module `Services/` classes | Business logic: state machine, points commit, redemption, fill level |
| | Nginx 1.26 + PHP 8.4-FPM | Serve the app, native via `apt` |
| | Laravel scheduler (one cron entry) | Session timeout sweeps, scheduled reports |
| **Data** | MariaDB 11.8 LTS, InnoDB | Persistent storage, atomic commits, foreign keys |
| | Eloquent models + migrations | Schema, defined in code (no hand-written `schema.sql`) |
| | `mariadb-dump` (MariaDB's `mysqldump`) | Routine backups |
| | Full Pi disk image, booted from USB SSD | Disaster recovery if boot media fails |
| **External Services** | Cloudflare Tunnel (`cloudflared` as a systemd service) | Dashboard reachable beyond the local network until handover |
| | A domain | Stable Tunnel hostname and email sender address; makes either handover path a DNS change |
| | Resend | Email delivery until handover, replaced by the university's SMTP afterwards |
| | GitHub | Version control; what gets handed over if the Pi needs rebuilding |

**Version policy:** use the newest versions that Raspberry Pi OS itself ships and
patches, not the absolute newest. The Pi then stays maintainable with plain
`apt upgrade` after handover, with no third-party repositories.

| Component | Version | Source | Supported until (checked 2 Oct 2026) |
|---|---|---|---|
| OS | Raspberry Pi OS Lite 64-bit (Trixie) | Raspberry Pi Imager | Into 2030 |
| PHP | 8.4 | `apt` | Security fixes to 31 Dec 2028 |
| Laravel | 13 | Composer | Security fixes to 17 Mar 2028 |
| MariaDB | 11.8 LTS | `apt` | Community maintenance to June 2028 |
| Nginx | 1.26 | `apt` | Tied to Debian 13 |
| Tailwind CSS, Vite | Whatever a fresh Laravel 13 project installs | npm | Not pinned separately |

Commit `composer.lock` and `package-lock.json` so a rebuild gets the exact versions
that were tested.

**Not in use:** Docker (Laravel Sail), PHP 8.5 and MariaDB 12.3 (both need
third-party repositories on the Pi), queues, Redis.

**Cut deliberately:** Bootstrap (Tailwind only), Chart.js and fetch()-driven CRUD
pages (plain Blade forms and tables instead).

---

## 4. Modular Monolith — Module Breakdown

One Laravel app, one database, organized into five modules by domain. This is a
modular monolith with a shared database: write ownership is enforced per module,
and reads are shared.

| Module | Owns | Logic lives in | Covers |
|---|---|---|---|
| **Identity** | `organizations`, `users`, `user_qr_credentials` | Laravel's built-in auth | Login and logout, password setup and reset by email, student profile and QR, admin user and organization management |
| **Devices** | `devices`, `bin_telemetry` | `BinService`, signature middleware | Device config, telemetry, fill level, admin bin monitoring, registering a bin, entering its calibration values and rotating its secret |
| **Deposits** | `deposit_sessions`, `transactions` | `DepositService` | Open session, deposit, complete, cancel, expiry, student history, admin transaction list and void |
| **Rewards** | `reward_rules`, `point_ledger`, `rewards`, `redemptions` | `PointsService`, `RedemptionService` | Points rate, balance, rewards catalogue, redeeming, admin fulfilment and cancellation, reward management |
| **Reporting** | Nothing | Read-only queries | Admin dashboard and its live summary, leaderboards, reports |
| *Notifications* | `notifications` | Event listeners | Reacts to deposit completed, redemption fulfilled and bin full; notification list and mark-as-read for students and admins |

Notifications is a supporting component, not a domain module. It exposes no
service and nothing depends on it.

**Accounts:** admins create student accounts one at a time or by CSV
import; there is no self-registration. A new account gets an email with a link to
set its password, and "Forgot password" sends the same kind of link, both through
Laravel's built-in password reset. Admins can also set a password from the user
management page, which is the fallback when email can't be sent.

A new account has no password: `users.password` stays NULL until the student sets
one, and login fails for an account with no password. No default password is ever
assigned or emailed. The user list shows which accounts have not been activated, so
an admin can resend the email. The links expire; a student who misses one uses
"Forgot password" to get a new link.

**Boundary rules:**
1. **Writes go through the owning module's service.** Deposits never inserts into
   `point_ledger`; it calls `PointsService` inside the same database transaction.
   The same applies to voids and redemptions. `PointsService` is the only code that
   writes `point_ledger`.
2. **Reads can cross modules.** Reporting and Eloquent relationships query whatever
   they need.
3. **Modules announce, they don't call Notifications.** They dispatch events
   (`DepositCompleted`, `RedemptionFulfilled`, `BinFull`) and listeners react.

Folder layout:
```
app/Modules/
  Identity/  Devices/  Deposits/  Rewards/  Reporting/
  Notifications/
```
Each module: `Models/`, `Http/Controllers/`, `Http/Requests/`, `Services/`,
`Events/`, `routes/web.php`, and `routes/device.php` where it has device endpoints.
One `ModuleServiceProvider` loads them: web routes with the `web` middleware
group, device routes under `/api/v1/device` with the signature middleware.

---

## 5. API Design and Business Rules

Three callers, two kinds of route:

| Caller | Routes | Auth |
|---|---|---|
| ESP32 | `/api/v1/device/*`, JSON, stateless | HMAC-SHA256 signature + timestamp |
| Student | Web routes (Blade pages and form posts) | Session cookie + CSRF token |
| Admin | Web routes under `/admin` | Same session, with a role check on every route |

Only the device endpoints are a JSON API. The admin dashboard's live summary is the
one JSON web route; it uses the normal session, not the device scheme.

The detailed design (request and response bodies, session state transitions and
the web route list) is in `API_Design.md`.

### 5.1 Device endpoints

| Method | Path | Module | Purpose |
|---|---|---|---|
| GET | `/device/time` | Devices | Server time for clock sync. Unsigned |
| GET | `/device/config` | Devices | Minimum and maximum weight, session timeout, fill threshold, load cell calibration |
| POST | `/device/telemetry` | Devices | Ultrasonic distance reading |
| POST | `/device/sessions` | Deposits | Open a session from a QR token |
| POST | `/device/sessions/{id}/deposit` | Deposits | Weight and verification result |
| POST | `/device/sessions/{id}/complete` | Deposits | Actuator result; commits points |
| POST | `/device/sessions/{id}/cancel` | Deposits | Cancel an open session |

### 5.2 Request signing

Every device request except `/device/time` carries three headers:

```
X-Device-Code: BIN-01
X-Timestamp:   1790956800
X-Signature:   hex( HMAC-SHA256( secret,
                 timestamp + "\n" + METHOD + "\n" + path + "\n" + raw_body ) )
```

- The server looks up the device by code, recomputes the signature over the **raw
  request body** with the stored secret, and compares with `hash_equals`.
- Only a device whose `status` is `active` is accepted. An `inactive` or `retired`
  device gets `DEVICE_INACTIVE`. An unknown device code gets the same
  `INVALID_SIGNATURE` answer as a bad signature.
- Requests whose timestamp is outside the signature window (Section 6.3) are
  rejected.
- The ESP32 has no clock. It fetches `/device/time` at boot and after any
  `CLOCK_SKEW` error, then counts forward from that.
- Both sides must sign the raw body bytes, never re-encoded JSON.
- The secret never crosses the wire, so the scheme is the same over plain HTTP on
  the LAN and over HTTPS.

**Build shortcut:** device auth lives in one middleware. It may start as a plain
Bearer key while the deposit flow is being built, and switch to HMAC before
integration testing. Nothing else in the app changes.

### 5.3 Core rules

1. The server computes points from grams: `floor(net_weight_g × points_per_gram)`,
   using the reward rule in effect when the session opened. The device never sends
   points.
   The full mechanism is in Section 5.7.
2. Points are credited only after verification passes **and** the actuator
   confirms the transfer.
3. `complete` is idempotent by session. `transactions.session_id` is unique, so a
   session can produce only one transaction, and no separate key is needed. A retry
   returns the original 200 response, not an error.
4. Other device writes are guarded by the session state machine
   (`OPEN → ACCEPTED → COMPLETED`, or `REJECTED/FAILED/EXPIRED/CANCELLED`), so a
   replayed request can't advance or duplicate a session. Each transition locks
   the session row and checks its state first, so two requests can't both act on
   the same state.
5. The server computes fill level from `distance_mm` and the bin's
   `empty_distance_mm`. The device only reports the raw distance.
6. Only `OPEN` sessions expire. Once a session is `ACCEPTED` the item may already
   be in the bin, so the session waits for `complete`. The ESP32 saves an unsent
   `complete` to non-volatile storage and resends it when the server is reachable
   again, including after a reboot. An `ACCEPTED` session with no `complete` within
   the review threshold (Section 6.3) is listed for admin review, and is never
   expired silently.
7. A weight below the rule's minimum or above its maximum is rejected before the
   actuator moves.
8. A student who has reached the daily points cap can't open a session.

### 5.4 Example flow

```http
POST /api/v1/device/sessions
{ "qr_token": "k7Qx…", "fw": "1.0.3" }
→ 201 { "session_id": "b3f1…", "expires_in_s": 90, "user": { "display_name": "Juan" } }

POST /api/v1/device/sessions/b3f1…/deposit
{ "weight_g": 184.6, "weight_stable": true, "samples": 20,
  "verification": { "method": "cam_diff", "passed": true, "score": 0.87 } }
→ 200 { "accepted": true, "points_preview": 18 }

POST /api/v1/device/sessions/b3f1…/complete
{ "actuator_ok": true, "cycle_ms": 3200 }
→ 200 { "transaction_id": 1042, "points_awarded": 18, "balance": 240 }
```

The numbers in this example only show the shape of the messages. Real point values
come from the reward rule in effect (Section 5.7).

### 5.5 Errors

**Error shape:** `{ "error": { "code": "BIN_FULL", "message": "...", "retryable": false } }`
— firmware switches on `code`, never on the message.

| Condition | HTTP | `code` |
|---|---|---|
| Bad or missing signature | 401 | `INVALID_SIGNATURE` |
| Timestamp outside the window | 401 | `CLOCK_SKEW` |
| Device is inactive or retired | 403 | `DEVICE_INACTIVE` |
| Invalid/unknown QR | 401 | `INVALID_QR` |
| Inactive user | 403 | `USER_INACTIVE` |
| Bin full | 409 | `BIN_FULL` |
| Duplicate/active session | 409 | `DUPLICATE_TRANSACTION` |
| No item / below min weight | 422 | `NO_ITEM` / `ZERO_WEIGHT` |
| Above max weight | 422 | `OVER_MAX_WEIGHT` |
| Daily points cap reached | 429 | `DAILY_LIMIT_REACHED` |
| No reward rule in effect | 503 | `NO_REWARD_RULE` |
| Verification failed | 422 | `VERIFICATION_FAILED` |
| Session timed out | 410 | `SESSION_EXPIRED` |
| DB failure | 503 | `DB_UNAVAILABLE` (with `Retry-After`) |
| Actuator failure | 200 | `actuator_ok:false`, session → `FAILED`, no points |

**Network failure policy:** fail closed. If the server is unreachable at the QR step,
reject and don't actuate. After an item is physically deposited, retry `complete`
up to 3× with backoff. If all three fail, keep the request
in non-volatile storage, show the student that the points are pending, and resend
it before accepting the next deposit (rule 6 in Section 5.3).

**QR code:** opaque random token shown on the student's profile, not the student ID
(guessable/forgeable). One active token per user, created with the account;
regenerating it replaces the old one.

**What a scan allows:** it identifies the student for one deposit and nothing else.
It can't spend points or open the account. Redeeming rewards and everything else
needs the website login, which is why the bin asks for no password or PIN.

### 5.6 Web pages

| Role | Pages |
|---|---|
| Student | Login, set or reset password, profile and QR, points balance, transaction history, organization info, leaderboard, rewards, redeem, my redemptions, notifications |
| Admin | Dashboard with live summary, users (with CSV import and resending the set-password email), organizations, transactions and void, total collected weight, bins and fill history, device registration, rewards, redemption fulfilment and cancellation, points rate, organization leaderboard, reports, notifications |

**Leaderboard scope:** the user leaderboard includes everyone (students and
admins; those are the only two roles). The organization leaderboard counts
students only. Admins have no `organization_id`, so filter on `role = 'student'`
before grouping rather than letting a `NULL` org group appear.

**Leaderboard metric:** rank by points earned, not by current balance. Points
earned is the sum of a user's `EARN` ledger entries minus the reversals of voided
transactions. Redeeming a reward never lowers a rank. An organization's score is
the total for the students currently in it.

**Total collected weight:** the sum of `net_weight_g` over deposits whose
transaction is `COMPLETED`. Voided deposits are excluded.

### 5.7 Reward point mechanism

Points are a single linear rate on verified weight, bounded by a minimum, a
maximum and a daily cap:

```
points = floor( net_weight_g × points_per_gram )
```

**Variables.** No values are set in this plan. The first four are columns on the
reward rule, set by an admin on the points-rate page. `points_cost` is set per
reward on the rewards page. The chosen values need instructor approval
(guidelines, Step 9).

| Variable | Stored in | Meaning |
|---|---|---|
| `points_per_gram` | `reward_rules` | Conversion rate from grams to points |
| `minimum_weight_g` | `reward_rules` | Smallest deposit that is accepted |
| `maximum_weight_g` | `reward_rules` | Largest deposit that is accepted. Empty means no limit |
| `daily_points_cap` | `reward_rules` | Most points one student can earn in a day. Empty means no cap |
| `points_cost` | `rewards` | Price of each reward in points |

**Constraints between the variables,** checked when an admin saves a rule:
- `minimum_weight_g` ≥ 1 ÷ `points_per_gram`, so an accepted deposit never earns
  zero points.
- `maximum_weight_g` > `minimum_weight_g`.
- `maximum_weight_g` is no more than the load cell, platform and actuator can
  handle.

A rule must be in effect for the bin to accept deposits. The admin creates the
first rule after setup, and until then every session is refused with
`NO_REWARD_RULE`.

**Order of checks for one deposit.** A failure at any step ends the session with no
points.

| Step | Check | Decided by | On failure |
|---|---|---|---|
| 1 | QR token belongs to an active user | Server | `INVALID_QR`, `USER_INACTIVE` |
| 2 | Bin is not full | Server | `BIN_FULL` |
| 3 | A reward rule is in effect, and the student is under its `daily_points_cap` | Server | `NO_REWARD_RULE`, `DAILY_LIMIT_REACHED` |
| 4 | An item is present | Device, reported to server | `VERIFICATION_FAILED` |
| 5 | Weight is stable and between minimum and maximum | Server | `NO_ITEM`, `ZERO_WEIGHT`, `OVER_MAX_WEIGHT` |
| 6 | Points are calculated | Server | — |
| 7 | Actuator confirms the transfer | Device, reported to server | Session `FAILED` |
| 8 | Points are credited | Server | — |

**Rules:**
1. **One rate for every item.** The camera confirms that something is on the
   platform, not what it is, so a higher rate for some items could not be verified.
2. **Round down, whole points only.** The system never awards more than was
   weighed.
3. **The server calculates.** The device sends grams and never sends points.
4. **Credit comes last.** Points are written only after verification passes and the
   actuator confirms, in the same database transaction as the transaction record.
5. **The daily cap is checked when a session opens.** It counts the points from the
   student's completed deposits since midnight, Philippine time. A student below the
   cap gets full credit for the next deposit, even if that deposit passes the cap.
   A voided deposit still counts toward that day's cap.
6. **Rules are versioned, and a session uses one rule.** Changing any of the four
   rule variables closes the current rule and creates a new one. A session uses the
   rule that was in effect when it opened, for the cap, the weight limits, the
   preview and the points, even if `complete` arrives much later. Each transaction
   stores that rule, so past points never change.
7. **Corrections add rows.** A void writes a reversal to the ledger; nothing is
   edited or deleted. The balance is always the sum of the ledger.

**Pricing rewards.** Decide how many grams should earn the cheapest reward, then
`points_cost` = that weight × `points_per_gram`. Price the other rewards relative
to it. Changing a reward's price doesn't affect past redemptions; each one stores
the cost it was redeemed at.

**Known limitation.** Weight is the only thing measured, so a heavy object that is
not e-waste earns the same as real e-waste. Three controls limit this:
`maximum_weight_g`, `daily_points_cap`, and the admin void. State this in the
report's scope and limitations.

**Left out on purpose:** item categories, tiered rates, streaks and bonuses. None
can be verified by this hardware, and each adds rules to test and defend.

---

## 6. Database

MariaDB 11.8, **InnoDB** storage engine:
- Enforces foreign keys (`transactions` → `deposit_sessions`, `point_ledger` →
  `transactions`/`redemptions`, `deposit_sessions` → `users`/`devices`).
- Atomic multi-table commits — the points-award step writes `transactions` and
  `point_ledger` as one unit; a crash mid-write can't leave a transaction row with
  no corresponding ledger entry, or vice versa.
- `point_ledger` is append-only: no `points_balance` column anywhere; a balance is
  always `SUM(points_delta)`, and a void/reversal is a new row, never a delete.

**Character set:** create the database explicitly as `utf8mb4` with
`utf8mb4_unicode_ci`, and set `DB_COLLATION` to the same. MariaDB 11.8's own default
collation does not import into MySQL or older MariaDB, which would break the
handover dump.

**Time zone:** store every timestamp in UTC. Set `'timezone' => '+00:00'` on the
Laravel database connection, and convert to Asia/Manila only when displaying.
Without this, timestamps follow the host's time zone and shift by eight hours when
the dump is restored on a server set to UTC.

**Tables:** there are 12 application tables; Laravel adds its own framework
tables (`migrations`, `sessions`, `cache`, `jobs`, and `password_reset_tokens`
for the reset links). The full definition is in
`Database_Schema.md`, with the diagram in
`EMBL02E_Smart_EWaste_ERD_v4.mmd`. This section is the rationale; the schema file
is the source of truth.

### 6.1 Ledger and concurrency rules

- **Redeeming:** inside one database transaction, lock the user's row
  (`SELECT … FOR UPDATE`), sum the ledger, then insert the redemption and its
  ledger row. Without the lock, two simultaneous redemptions can overdraw.
- **Lock first:** take the lock before any other read in the transaction. InnoDB
  reads from a snapshot taken at the transaction's first plain read, so a sum
  read after an earlier plain read can miss rows another request just committed.
- **Status changes:** every status change on a session, a redemption or a
  transaction locks that row and checks its current status in the same database
  transaction. Two requests can't both act on the same state, so a redemption
  can't be both fulfilled and cancelled, and a cancelled session can't later be
  accepted.
- **New sessions:** the checks and the insert run in one database transaction
  that locks the bin's device row, then the user's row. Two scans can't both pass
  the active-session or daily-cap check.
- **Stock:** decrement with `WHERE stock_quantity >= ?` and check the affected row
  count, so stock can't go negative.
- **Negative balances:** voiding a transaction whose points were already
  spent leaves the balance below zero. Allow it. The student can't redeem again
  until new deposits bring the balance back above a reward's cost.
- **Cancelling a redemption:** an admin can cancel a redemption while it
  is `PENDING`. In one database transaction the status becomes `CANCELLED`, the
  stock is returned, and a `REVERSAL` row gives the points back. A `FULFILLED`
  redemption can't be cancelled.

### 6.2 Mapping to the guidelines' suggested tables

| Guidelines suggest | This design |
|---|---|
| `users`, `admins` | `users` with a `role` column |
| `organizations` | `organizations` |
| `transactions` | `transactions` |
| `ewaste_records` | `deposit_sessions` (weight, verification, actuator result) |
| `reward_points` | `point_ledger` |
| `rewards`, `redemptions` | `rewards`, `redemptions` |
| `bin_status` | `bin_telemetry` + thresholds on `devices` |
| `notifications` | `notifications` |

### 6.3 Settings that are not in the database

These are application settings, not data. They live in one config file
(`config/ewaste.php`), take their values from `.env`, and are read with `config()`.

| Setting | Starting value | Used for |
|---|---|---|
| Session timeout | 90 seconds | How long an `OPEN` session lasts; also returned by `/device/config` |
| Review threshold | 10 minutes | How long an `ACCEPTED` session waits for `complete` before it is listed for admin review |
| Signature window | 60 seconds | How far a device timestamp may be from server time |
| Display time zone | Asia/Manila | Converting stored UTC times for display, and the midnight used by the daily cap |

Values that an admin changes while the system runs stay in the database: the four
rule variables, each reward's price, and each bin's fill threshold and calibration.

---

## 7. Deployment Plan

**Local network:** the ESP32 and the Pi share a dedicated router. The Pi has a
reserved IP or an mDNS name. The ESP32 always talks to the Pi over the LAN with
plain HTTP and HMAC signing, so deposits never depend on internet access.

**Browsers:** students and admins use the tunnel hostname (HTTPS) as the single
canonical URL. LAN HTTP is for the device only. This keeps secure session cookies
on without conflict.

**Demo fallback:** with the tunnel as the only browser URL, the
dashboard is unreachable if campus internet or Cloudflare is down, even though the
bin keeps working. Keep a second `.env` file ready that sets `APP_URL` to the Pi's
LAN address and `SESSION_SECURE_COOKIE=false`, and rehearse switching to it once
before the final demonstration.

**Setup, native (no Docker):**
1. Flash Raspberry Pi OS Lite 64-bit (Trixie); boot from USB SSD, not SD card.
2. `apt install nginx mariadb-server php8.4-fpm php8.4-mysql php8.4-mbstring php8.4-xml php8.4-curl php8.4-bcmath`
3. `CREATE DATABASE ewaste CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`
   with a dedicated MariaDB user — not `root`.
4. Clone the repo, `composer install --no-dev`, fill in `.env`
   (`DB_CONNECTION=mariadb`, `DB_COLLATION=utf8mb4_unicode_ci`, `APP_URL` = tunnel
   hostname, `SESSION_SECURE_COOKIE=true`, `QUEUE_CONNECTION=sync`, and the mail
   settings from Section 7.2).
5. `php artisan migrate`, then seed the first admin account. Before first use, that
   admin registers the bin and creates the first reward rule; the bin refuses
   deposits until both exist.
6. Compile Tailwind via Vite on a dev machine; deploy the built assets.
7. Add the scheduler's cron entry: `* * * * * php /path/to/artisan schedule:run`.
8. Set up `cloudflared` as a systemd service with a named tunnel on the domain.
9. Schedule `mariadb-dump` backups (MariaDB's `mysqldump`); keep a full disk image
   of the configured Pi, and a copy of `.env` (it holds `APP_KEY`) somewhere safe.

### 7.1 Ingress now and after handover

Cloudflare Tunnel is swappable network infrastructure, not an application
dependency. Laravel never calls a Cloudflare-specific API.

| | Now, until handover | Path A: stays on the Pi | Path B: school's platform |
|---|---|---|---|
| Browser ingress | Cloudflare Tunnel | ISP public IP, port forward, Certbot TLS | The school's host and TLS |
| App host | Pi | Pi | School server |
| Device to server | LAN HTTP + HMAC | LAN HTTP + HMAC | HTTPS + HMAC over the internet |
| What changes | — | Router, DNS, TLS certificate | Deploy repo, import dump, re-point each bin |
| App code changes | — | None | None |

On Path B the bin stops accepting deposits when the internet is down, because the
network policy is fail closed. Document this for the school.

**Real client IP, done portably:** don't read Cloudflare's `CF-Connecting-IP`
header in app code. Configure Laravel's `TrustProxies` middleware to trust the
local Nginx hop and read the standard `X-Forwarded-For` / `X-Forwarded-Proto`
headers. This keeps login rate-limiting and audit logs correct under any of the
three ingress setups.

### 7.2 Email

Laravel's mailer is the adapter. Application code sends mail only through
Laravel's `Mail` and `Notification` classes, and the delivery service is chosen by
`MAIL_MAILER` in `.env`. No custom adapter is written, the Resend SDK is never
called directly, and no Resend-only feature (templates, tags, webhooks) is used.

| | Now and for the demo | After handover |
|---|---|---|
| Delivery | Resend, over its HTTPS API | The university's SMTP server |
| `.env` | `MAIL_MAILER=resend`, `RESEND_API_KEY` | `MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD` |
| Sender | `MAIL_FROM_ADDRESS` on the project domain | `MAIL_FROM_ADDRESS` on the university domain |
| Needs | The `resend/resend-php` package; the domain verified in Resend with DNS records | SMTP credentials from the school's IT office |
| App code changes | — | None |

- **Scope:** two emails only — set your password (new account) and
  forgot password. Notifications stay in-app in the `notifications` table.
- **The domain is required for this.** Without a verified domain, Resend delivers
  only to the account owner's own address.
- **Free plan limits:** 100 emails a day and 3,000 a month, with one domain. A CSV
  import of more than 100 students has to be invited over several days, or the
  rest given passwords by an admin.
- **No queue.** Mail is sent during the request. A failed send must not fail the
  action: catch it, tell the admin the email was not sent, and use the admin
  password fallback.
- **Email needs the internet.** On the LAN demo fallback, or in any outage, no
  email goes out; the bin and the dashboard still work.
- **Development and tests** use `MAIL_MAILER=log`, which writes emails to the log
  instead of sending them.

---

## 8. Portability / Handover Plan

What keeps this portable:

- **The Pi is a server only.** No camera, display or GPIO work on it, so the app
  can leave it.
- **No Pi-specific code.** Eloquent only with no MariaDB-specific SQL, files
  through Laravel's storage disk, all URL, proxy and cookie settings in `.env`.
- **Reproducible schema and data.** Laravel migrations, plus a dump in a portable
  collation and in UTC. Current `mariadb-dump` writes a sandbox-mode comment as the
  first line, which fails on MySQL and older MariaDB clients with
  `Unknown command '\-'`. Strip that line from the handover dump, and test one
  import into MySQL before handover.
- **`APP_KEY` travels with the data.** Device secrets are encrypted with it. A new
  host must reuse the same key, or every bin has to be issued a new secret.
- **Re-pointable firmware.** Wi-Fi credentials, `API_BASE_URL`, device code and
  secret live in the ESP32's non-volatile storage, set through a setup portal
  (WiFiManager or similar). No reflashing to change the server. TLS is switched on
  by an `https://` base URL.
- **Swappable services.** The app depends on Laravel's interfaces, never on a
  named service, so each one can be replaced without touching the modules
  (Section 8.1).
- **Swappable hardware.** Each part of the bin sits behind its own firmware
  module (Section 8.2).
- **Minimal host requirements.** PHP 8.4, MySQL or MariaDB, and one cron entry.

**At handover, give the school:**
1. The Git repo: the web app with its migrations, `.env.example` and lock files,
   and the three firmware programs.
2. A dump of final data, with the sandbox line removed.
3. One `handover.md` — how to run `composer install` and `php artisan migrate`,
   point `.env` at their database, carry `APP_KEY` over (never run `key:generate`
   on a host that has existing data), add the cron entry, switch the mailer to
   the university's SMTP, re-point a bin through its setup portal, and issue a bin
   a new secret.
4. The physical Pi, already configured and running, and the bin.
5. The domain, the Cloudflare account and the Resend account, registered under a
   transferable account (a group or school email, not a personal one). The Resend
   account can be closed once the school's SMTP is in use. If the domain came from
   a student offer tied to one person, the school replaces it with a name of its
   own at handover, which changes `APP_URL`, DNS and the mail sender.

### 8.1 Swappable services

**Rule:** application code depends on Laravel's interfaces, never on a specific
service. A service's name appears only in `.env` and `config/`. No custom adapter
classes are written where Laravel already has a driver. Two checks keep this true:

- No vendor SDK is imported anywhere in `app/` (nothing from Resend, Cloudflare or
  a cloud provider).
- `env()` is called only inside `config/`. Everything else reads `config()`.

| Concern | App code uses | Now | Can become | Change needed |
|---|---|---|---|---|
| Database | Eloquent and migrations | MariaDB 11.8 | MySQL on the school's server | `.env`, then import the dump |
| Email | `Mail`, `Notification` | Resend | University SMTP | `.env` (Section 7.2) |
| Sessions | Laravel sessions | Database | File or Redis | `.env` (`SESSION_DRIVER`) |
| Cache | `Cache` | Database | File or Redis | `.env` (`CACHE_STORE`) |
| Logging | `Log` | Daily files on disk | The school's log server | `.env` (`LOG_CHANNEL`) |
| File storage | `Storage` | Local disk | Network or S3-compatible storage | `.env` (`FILESYSTEM_DISK`), then move the files |
| Background jobs | Laravel queue | `sync`, runs during the request | Database queue | `.env`, plus a worker service on the host |
| Browser ingress | `APP_URL`, standard forwarded headers | Cloudflare Tunnel | Port forward with Certbot, or the school's host | Network and DNS (Section 7.1) |
| Notification channels | Events and listeners | In-app `notifications` table | Email or SMS as well | Add one listener; modules unchanged |
| Login | Laravel's auth guard; users keyed by email | Email and password | University single sign-on | Add Laravel Socialite and one controller |
| Device connection | `API_BASE_URL` stored on the bin | LAN HTTP | HTTPS to another host | The bin's setup portal |
| Device authentication | One middleware | HMAC signing | Another scheme | One class |
| Item verification | `method`, `passed`, `score` in the API | ESP32-CAM frame differencing | Another camera or sensor | Firmware only; the server is unchanged |
| Bin display | Lines of text over a serial link | ESP32-S3 panel board with LVGL | Another display | Display firmware only; the controller is unchanged |

Three of these are more than a settings change: file storage needs the files
moved, background jobs need a worker process and change how a failed email is
reported, and single sign-on needs a small amount of new code.

**Not made swappable, on purpose:** Laravel itself, Blade and Tailwind, and the
MySQL family of databases (PostgreSQL is not a target). Abstracting these would
add code with no realistic use.

**Notifications table:** the in-app `notifications` table is this project's own
and is written by listeners. Don't generate Laravel's built-in notifications
table, which uses the same name with different columns.

### 8.2 Firmware modules

The same idea on the ESP32: each hardware part sits behind one module with a
small, fixed set of functions, so replacing a part changes one file.

| Module | Hides | Gives the main loop |
|---|---|---|
| `scanner` | QR scanner and its serial link | The scanned token |
| `scale` | Load cell, amplifier, calibration | Stable weight in grams |
| `verifier` | ESP32-CAM link | Passed or failed, and a score |
| `actuator` | Motor driver and limit switches | Success or failure, and cycle time |
| `bin_level` | Ultrasonic sensor | Distance in millimetres |
| `feedback` | The serial link to the panel board, LEDs, buzzer | Screen changes, touch events and status signals |
| `api` | Wi-Fi, HTTP, HMAC signing, retries, the saved unsent `complete` | One function per device endpoint |
| `settings` | Non-volatile storage and the setup portal | Server URL, device code, secret |

Pin numbers live in one header file. Only `api` knows about HTTP, and only
`settings` knows where the server is.

**Three firmware programs.** The modules above are the controller firmware. The
other two programs are small:

| Program | Board | Job |
|---|---|---|
| Controller | NodeMCU | Runs the deposit sequence and talks to the server |
| Camera | ESP32-CAM | Answers "is an item present?" with passed or failed, and a score |
| Display | ESP32-S3 panel board | Draws the screens with LVGL and reports touches |

The controller sends the panel one line of text per screen change: the screen name
and the values to show. The panel sends back one line per touch. It never talks to
the server or reads a sensor, so it holds no state that matters.

Screens: idle, welcome, weighing, accepted, done, points pending, problem (with
the reason), bin full and offline. Touch is used for one thing at first: a Cancel button, which
makes the controller call the cancel endpoint.

**Repository layout.** One Git repository with a folder for each
program: `web/` for the Laravel app, `firmware/controller/`, `firmware/camera/`,
`firmware/display/`, `tools/simulator/` and `docs/`.

---

## 9. Conceptual Framework (IPO Model with Feedback)

```
┌─────────┐      ┌───────────┐      ┌──────────┐
│  INPUT  │ ───► │  PROCESS  │ ───► │  OUTPUT  │
└─────────┘      └───────────┘      └──────────┘
     ▲                                    │
     └──────── feedback: bin status, ─────┘
               dashboard monitoring,
               admin rate adjustments
```

| Stage | Items |
|---|---|
| Input | Student QR code, e-waste item placed, measured weight, camera image, bin-level reading |
| Process | Authenticate user, measure & calibrate, verify item, compute points, automated transfer, record transaction |
| Output | Reward points, transaction record, result on the bin's display, dashboard update, bin-level status, system notification |

The feedback loop is what makes this a *monitoring* system: bin-level data and
dashboard activity shape the next transaction and any admin adjustment (e.g.
points rate).

---

## 10. Open Items

**Team decisions still to confirm:**
- [ ] Display board: confirm the exact model of the ESP32-S3 7" panel board, and
      how its serial connector is selected on that board.
- [ ] Domain: get one before the tunnel and email are set up. The options are a
      first-year-free domain from the GitHub Student Developer Pack, or a low-cost
      paid one. It is needed for email as well as for a stable tunnel hostname.
- [ ] Accounts: admins create student accounts, students set their password by
      email, and there is no self-registration (Section 4).
- [ ] Email scope: password setup and reset only; notifications stay in-app
      (Section 7.2).
- [ ] Demo fallback: a LAN `.env` for when the internet is down (Section 7).
- [ ] Negative balances: allowed after a void (Section 6.1).
- [ ] Redemptions: an admin can cancel one while it is pending (Section 6.1).
- [ ] Leaderboards: ranked by points earned, not by current balance (Section 5.6).
- [ ] Collected weight: voided deposits are excluded (Section 5.6).
- [ ] Daily cap: voided deposits still count toward the day's cap (Section 5.7).
- [ ] Touchscreen: a Cancel button only, at first (Section 8.2).
- [ ] Repository: one repo with a folder per program (Section 8.2).

**Before Checkpoint 1:**
- [ ] Choose the actuator model and check its stall current against the driver.
- [ ] Verify the NodeMCU pin map on the bench (Section 2).
- [ ] Set the five point variables and get them approved (Section 5.7; guidelines,
      Step 9): `points_per_gram`, `minimum_weight_g`, `maximum_weight_g`,
      `daily_points_cap`, and each reward's `points_cost`.
- [ ] Finalize exact hardware models and get Checkpoint 1 approval.

**Build order:**
- [ ] Add the two swappability checks to the repo: no vendor SDK imports in
      `app/`, and no `env()` outside `config/` (Section 8.1).
- [ ] Write the OpenAPI spec for the seven device endpoints and serve via Swagger UI.
- [ ] Build a device simulator that signs requests, to test retries, expired
      sessions and repeated `complete` calls before relying on real hardware.
      Do this first, so firmware and web work can proceed in parallel.
