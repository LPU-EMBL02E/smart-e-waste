# IoT-Based Smart E-Waste Collection and Monitoring System
### EMBL02E API Design — Device API and Web Routes

---

## 1. Scope and Sources

This file covers the two kinds of route the system has:

- **The device API:** the seven JSON endpoints under `/api/v1/device` that the bin's
  controller calls.
- **The web routes:** every student and admin page and form post, including the
  admin dashboard's live summary, which is the one JSON web route.

It is written from `System_Plan.md`, `Database_Schema.md`
and `EMBL02E_Smart_EWaste_ERD_v4.mmd`. Those files stay the source of truth for
business rules and tables. This file adds the wire-level detail needed to build.

**How to read it:**

- Text with no tag restates the plan or the schema.
- A tag like **[P7]** marks a detail the plan and schema do not state. It is a
  proposal. All of them are listed in Section 9 for the team to confirm.
- Section 10 lists questions that have no proposal, because answering them needs
  information that is not in the plan or the schema.
- Numbers in examples (weights, points, tokens, calibration values) only show the
  shape of a message. The plan sets no point values.

**Laravel behaviour this design relies on:** the password reset routes and their
names were checked against the Laravel 13 documentation. Form method spoofing
(`@method`) and CSRF protection on the `web` middleware group are long-standing
Laravel behaviour and were not re-checked against the 13 documentation.

---

## 2. Callers

| Caller | Routes | Auth |
|---|---|---|
| ESP32 controller (NodeMCU) | `/api/v1/device/*`, JSON, stateless | HMAC-SHA256 signature + timestamp |
| Student | Web routes (Blade pages and form posts) | Session cookie + CSRF token |
| Admin | Web routes under `/admin` | Same session, with a role check on every route |

Only the NodeMCU talks to the server. The ESP32-CAM and the panel board never do.

---

## 3. Device API Conventions

### 3.1 Base URL and format

- **Base URL:** the bin stores `API_BASE_URL` in non-volatile storage. It holds the
  scheme, host and optional port only, for example `http://192.168.1.10`. The
  firmware appends `/api/v1/device/...` **[P1]**.
- **Bodies:** JSON in UTF-8. A request with a body sends
  `Content-Type: application/json`. The server answers every device route with
  JSON, including validation failures. It never answers a device route with a
  redirect or an HTML page **[P2]**.
- **Numbers:** weights, distances and percentages are JSON numbers, not strings
  **[P3]**.
- **Times:** Unix seconds in UTC, as in the `X-Timestamp` header **[P3]**.

### 3.2 Request signing

Every device request except `GET /device/time` carries three headers:

```
X-Device-Code: BIN-01
X-Timestamp:   1790956800
X-Signature:   hex( HMAC-SHA256( secret,
                 timestamp + "\n" + METHOD + "\n" + path + "\n" + raw_body ) )
```

The exact inputs **[P4]**:

| Part | Value |
|---|---|
| Key | The bytes of the secret string exactly as issued. It is not hex-decoded first |
| `timestamp` | The same string sent in `X-Timestamp`: Unix seconds in decimal |
| `METHOD` | Upper case: `GET` or `POST` |
| `path` | The request path from `/api/v1/device` onward, with no scheme, host or query string. No device endpoint uses a query string |
| `raw_body` | The exact bytes sent. For a request with no body it is empty, so the signed string ends with `"\n"` |
| Output | Lower-case hex, 64 characters |

Both sides sign the raw body bytes, never re-encoded JSON. The secret never
crosses the wire, so the scheme is the same over plain HTTP on the LAN and over
HTTPS.

### 3.3 Order of checks in the signature middleware

**[P5]**

| Step | Check | On failure |
|---|---|---|
| 1 | All three headers are present and the device code exists | 401 `INVALID_SIGNATURE` |
| 2 | The recomputed signature equals `X-Signature` (compared with `hash_equals`) | 401 `INVALID_SIGNATURE` |
| 3 | The timestamp is within the signature window of server time | 401 `CLOCK_SKEW` |
| 4 | `devices.status` is `active` | 403 `DEVICE_INACTIVE` |

The signature is checked before the status, so only a caller that holds the
secret can learn that a device is inactive. An unknown device code gets the same
answer as a bad signature.

After step 4 the middleware sets `devices.last_seen_at` **[P6]**.

The ESP32 has no clock. It calls `GET /device/time` at boot and after any
`CLOCK_SKEW` error, then counts forward from that.

### 3.4 Signing test vectors

Use these to check the firmware and the middleware against each other. The secret
here is for testing only.

```
secret:    test-secret-do-not-use-0123456789abcdef
timestamp: 1790956800
```

**Vector 1, no body**

```
GET /api/v1/device/config

signed string: "1790956800\nGET\n/api/v1/device/config\n"
X-Signature:   30f42c3aca93595dedf2c7a61ad832036cf3de02cb9f8dce7f478b8e049b680f
```

**Vector 2, with a body**

```
POST /api/v1/device/sessions
{"qr_token":"TESTTOKEN0001","fw":"1.0.3"}

signed string: "1790956800\nPOST\n/api/v1/device/sessions\n{\"qr_token\":\"TESTTOKEN0001\",\"fw\":\"1.0.3\"}"
X-Signature:   8c7cd80ead0a593505a15b215d85476e1a897caf2e71baf11c1eeff699659e7f
```

The body in vector 2 is 41 bytes with no spaces and no trailing newline. Both
signatures were computed with Python's `hmac` module and confirmed with
`openssl dgst -sha256 -hmac`.

### 3.5 Error format

```json
{ "error": { "code": "BIN_FULL", "message": "The bin is full.", "retryable": false } }
```

- The firmware switches on `code`, never on `message`.
- `retryable` is `true` when the firmware may send the same request again with no
  action from the student. That is the case for `DB_UNAVAILABLE`, and for
  `CLOCK_SKEW` after the firmware has fetched the time again and re-signed. Every
  other code is `false` **[P7]**.
- `DB_UNAVAILABLE` comes with a `Retry-After` header. The value is 5 seconds
  **[P7]**.
- A reply the firmware cannot read as either a success body or this error shape is
  treated like no reply at all, and the network failure policy applies **[P9]**.

The full list of codes is in Section 6.

### 3.6 Replays and retries

There is no nonce. A captured request can be sent again inside the signature
window, and the firmware itself retries after a lost reply. Both are safe because
of the session state machine and the unique `transactions.session_id`:

| Request sent twice | Result |
|---|---|
| `POST /device/sessions` | The second call cancels the session the first one opened and opens a new one for the same user (Section 4.3). Only one session stays open and no points are involved |
| `deposit`, `complete`, `cancel` | The second call returns the outcome already recorded and changes nothing **[P10]** |
| `POST /device/telemetry` | A second reading is stored. Harmless |
| `GET` requests | Nothing is written apart from `last_seen_at` |

A replayed `POST /device/sessions` can interrupt a deposit that has not reached
`deposit` yet, and the student then scans again. It cannot credit points
**[P13]**.

**Network failure policy (firmware):** fail closed. If the server is unreachable at
the QR step, reject and do not actuate. After an item is physically deposited,
retry `complete` up to 3 times with backoff. If all three fail, keep the request
in non-volatile storage, show the student that the points are pending, and resend
it before accepting the next deposit.

---

## 4. Deposit Session State Machine

### 4.1 States and transitions

`deposit_sessions.status` takes seven values. `OPEN` and `ACCEPTED` are live; the
other five are final.

| From | To | Caused by |
|---|---|---|
| (none) | `OPEN` | `POST /device/sessions` passes every check |
| `OPEN` | `ACCEPTED` | `deposit` passes verification and the weight checks |
| `OPEN` | `REJECTED` | `deposit` fails verification or a weight check **[P11]** |
| `OPEN` | `CANCELLED` | `cancel`, or a new `POST /device/sessions` from the same device **[P13]** |
| `OPEN` | `EXPIRED` | The timeout sweep, or a device call that arrives after `expires_at` **[P12]** |
| `ACCEPTED` | `COMPLETED` | `complete` with `actuator_ok: true` |
| `ACCEPTED` | `FAILED` | `complete` with `actuator_ok: false` |

- Only `OPEN` sessions expire. An `ACCEPTED` session may already have the item in
  the bin, so it waits for `complete`. If none arrives within the review threshold
  it is listed for admin review, and is never expired silently.
- `closed_at` is set when a session reaches a final state **[P11]**.
- `failure_code` holds the error code for a `REJECTED` session and
  `ACTUATOR_FAILED` for a `FAILED` session. It stays NULL otherwise **[P11]**.
- **The rule a session uses** is the one in effect when it opened: the
  `reward_rules` row whose `effective_from` is at or before the session's
  `created_at` and whose `effective_to` is NULL or later. `deposit_sessions` has
  no rule column, so the server looks the rule up from `created_at`. Rules never
  overlap, so exactly one row matches. `transactions.reward_rule_id` stores it at
  completion.

### 4.2 What each call does in each state

**[P10]** for the replay cells, **[P8]** for `INVALID_SESSION_STATE`.

| Session state | `deposit` | `complete` | `cancel` |
|---|---|---|---|
| `OPEN`, before `expires_at` | Processed | 409 `INVALID_SESSION_STATE` | Processed |
| `OPEN`, after `expires_at` | Moved to `EXPIRED`; 410 `SESSION_EXPIRED` | Same | Same |
| `ACCEPTED` | 200 with the recorded result | Processed | 409 `INVALID_SESSION_STATE` |
| `COMPLETED` | 409 `INVALID_SESSION_STATE` | 200 with the original result | 409 `INVALID_SESSION_STATE` |
| `FAILED` | 409 `INVALID_SESSION_STATE` | 200 with the recorded failure result | 409 `INVALID_SESSION_STATE` |
| `REJECTED` | 422 with the recorded `failure_code` | 409 `INVALID_SESSION_STATE` | 409 `INVALID_SESSION_STATE` |
| `EXPIRED` | 410 `SESSION_EXPIRED` | 410 `SESSION_EXPIRED` | 410 `SESSION_EXPIRED` |
| `CANCELLED` | 409 `INVALID_SESSION_STATE` | 409 `INVALID_SESSION_STATE` | 200 with the recorded result |

A session id that does not exist, or that belongs to another device, gets 404
`SESSION_NOT_FOUND` **[P8]**.

A replayed `complete` returns the stored `transaction_id` and `points_awarded`.
Its `balance` is the balance at the time of the reply **[P10]**.

### 4.3 Active-session rule

**[P13]**

- **One open session per bin.** When `POST /device/sessions` passes every check,
  any earlier `OPEN` session of the calling device is closed first: `status` =
  `CANCELLED`, `closed_at` = now. This happens in the same database transaction
  that inserts the new session. A bin handles one deposit at a time, so a new
  scan means the earlier session was abandoned.
- **One open session per user.** The call is refused with 409
  `DUPLICATE_TRANSACTION` when the scanned user has an `OPEN` session on a
  different device that has not passed `expires_at`. With a single bin this never
  happens.
- **`ACCEPTED` sessions are left alone.** A new scan never closes one, and one
  never blocks a new scan. It waits for `complete`.
- **A request that fails a check changes nothing.** The earlier session stays
  open.

Before these checks, any `OPEN` session for that device or user that has passed
`expires_at` is moved to `EXPIRED` **[P12]**.

Consequences:

- If the reply to `POST /device/sessions` is lost, the student scans again and
  gets a new session at once.
- A student who scans and walks away does not block the next student.
- A later call on the closed session gets `INVALID_SESSION_STATE` (Section 4.2).

---

## 5. Device Endpoints

All paths are under `/api/v1/device`. Device routes are loaded by the
`ModuleServiceProvider` with the signature middleware, outside the `web`
middleware group, so they have no session and no CSRF check.

| Method | Path | Module | Signed |
|---|---|---|---|
| GET | `/device/time` | Devices | No |
| GET | `/device/config` | Devices | Yes |
| POST | `/device/telemetry` | Devices | Yes |
| POST | `/device/sessions` | Deposits | Yes |
| POST | `/device/sessions/{id}/deposit` | Deposits | Yes |
| POST | `/device/sessions/{id}/complete` | Deposits | Yes |
| POST | `/device/sessions/{id}/cancel` | Deposits | Yes |

Field rules in this section are **[P17]** unless they come from a column
definition in the schema.

### 5.1 GET /device/time

Server time for clock sync. Unsigned, and the only device route that is.

```http
GET /api/v1/device/time
→ 200 { "time": 1790956800 }
```

`time` is Unix seconds in UTC **[P14]**. Nothing is written.

### 5.2 GET /device/config

What the bin needs to run: weight limits, session timeout, fill threshold and load
cell calibration.

```http
GET /api/v1/device/config
→ 200 {
    "session_timeout_s": 90,
    "minimum_weight_g": 50.0,
    "maximum_weight_g": 2000.0,
    "fill_threshold_percent": 90.0,
    "calibration": {
      "weight_offset": 8412.5,
      "weight_scale_factor": 21.45,
      "calibrated_at": 1790950000
    }
  }
```

**[P15]** for the field names and the null rules.

| Field | Source | Null when |
|---|---|---|
| `session_timeout_s` | Session timeout setting in `config/ewaste.php` | Never |
| `minimum_weight_g` | `reward_rules.minimum_weight_g` of the rule in effect now | No rule is in effect |
| `maximum_weight_g` | `reward_rules.maximum_weight_g` of the rule in effect now | No rule is in effect, or the rule has no upper limit |
| `fill_threshold_percent` | `devices.fill_threshold_percent` | Never |
| `calibration.weight_offset` | `devices.weight_offset` | The bin has not been calibrated |
| `calibration.weight_scale_factor` | `devices.weight_scale_factor` | The bin has not been calibrated |
| `calibration.calibrated_at` | `devices.calibrated_at` | The bin has not been calibrated |

- The endpoint answers 200 even when no reward rule is in effect, so the bin can
  boot. Deposits are still refused at `POST /device/sessions` with
  `NO_REWARD_RULE`.
- The weight limits are for the bin's display only. The server decides whether a
  weight is accepted, using the rule the session opened under.
- The firmware fetches the config at boot and each time it sends telemetry
  **[P16]**.

### 5.3 POST /device/telemetry

One ultrasonic distance reading. The device reports the raw distance; the server
computes the fill level.

```http
POST /api/v1/device/telemetry
{ "distance_mm": 412.5 }
→ 201 { "fill_percent": 17.5, "status": "OK" }
```

The example assumes `devices.empty_distance_mm` is 500.

| Field | Rule |
|---|---|
| `distance_mm` | Required. Number, 0 or more, at most two decimal places |

**Server steps:**

1. `fill_percent` = `(empty_distance_mm − distance_mm) / empty_distance_mm × 100`,
   clamped to 0–100.
2. `status` = `FULL` when `fill_percent` ≥ `devices.fill_threshold_percent`,
   otherwise `OK`.
3. Insert the `bin_telemetry` row.
4. Dispatch `BinFull` when this row is `FULL` and the device's previous row was
   not, or there was no previous row **[P19]**. The listener writes one
   notification row per admin user.

The reply returns the computed values so the bin can show its bin-full screen
**[P18]**.

The firmware sends a reading at boot, after each session reaches a final state,
and on a fixed interval **[P16]**. The interval is not set here (Section 10).

### 5.4 POST /device/sessions

Opens a deposit session from a scanned QR token.

```http
POST /api/v1/device/sessions
{ "qr_token": "k7Qx…", "fw": "1.0.3" }
→ 201 { "session_id": "b3f1…", "expires_in_s": 90, "user": { "display_name": "Juan" } }
```

| Field | Rule |
|---|---|
| `qr_token` | Required. String, at most 64 characters (`user_qr_credentials.token`) |
| `fw` | Optional. String, at most 30 characters. Saved to `devices.firmware_version` **[P6]** |

**Checks, in order.** The first failure ends the request and no session row is
created.

| Step | Check | On failure |
|---|---|---|
| 1 | `qr_token` matches a `user_qr_credentials` row | 401 `INVALID_QR` |
| 2 | That user's `status` is `active` | 403 `USER_INACTIVE` |
| 3 | The bin is not full | 409 `BIN_FULL` |
| 4 | A reward rule is in effect now | 503 `NO_REWARD_RULE` |
| 5 | The user is under the rule's `daily_points_cap` | 429 `DAILY_LIMIT_REACHED` |
| 6 | The user has no active session on another device (Section 4.3) | 409 `DUPLICATE_TRANSACTION` |

- **Step 3:** the bin is full when the `fill_percent` of the device's latest
  `bin_telemetry` row is at or above `devices.fill_threshold_percent`. A device
  with no reading yet is treated as not full **[P20]**.
- **Step 5:** skipped when `daily_points_cap` is NULL. Otherwise the server sums
  `points_awarded` over the user's transactions created since midnight in the
  display time zone (Asia/Manila), and refuses when the sum has reached the cap.
  `transactions` has no user column, so the user comes from
  `deposit_sessions.user_id`. Voided transactions still count.
- **Step 6** is placed last **[P13]**.

**On success,** in one database transaction:

- Close any earlier `OPEN` session of this device as `CANCELLED` (Section 4.3)
  **[P13]**.
- Insert the `deposit_sessions` row: a UUID `id`, `user_id`, `device_id`,
  `status` = `OPEN`, `expires_at` = now + session timeout.
- Set `user_qr_credentials.last_used_at` **[P6]**.
- `expires_in_s` is the session timeout. `display_name` is `users.first_name`
  **[P21]**.

### 5.5 POST /device/sessions/{id}/deposit

Reports the weight and the verification result. The server decides whether the
item is accepted. The actuator must not move until this call returns
`accepted: true`.

```http
POST /api/v1/device/sessions/b3f1…/deposit
{ "weight_g": 184.6, "weight_stable": true, "samples": 20,
  "verification": { "method": "cam_diff", "passed": true, "score": 0.87 } }
→ 200 { "accepted": true, "points_preview": 18 }
```

| Field | Rule | Stored in |
|---|---|---|
| `weight_g` | Required. Number, at most two decimal places. The calibrated net weight from the bin's `scale` module | `net_weight_g` |
| `weight_stable` | Required. Boolean | `is_weight_stable` |
| `samples` | Required. Integer, 0 or more | `sample_count` |
| `verification.method` | Required. String, at most 50 characters | `verification_method` |
| `verification.passed` | Required. Boolean | `verification_passed` |
| `verification.score` | Optional. Number from 0 to 1, at most four decimal places | `verification_score` |

The six values are stored on the session whether the deposit is accepted or
rejected **[P22]**.

**Checks, in order,** using the rule the session opened under (Section 4.1):

| Step | Check | On failure |
|---|---|---|
| 1 | `verification.passed` is true | 422 `VERIFICATION_FAILED` |
| 2 | `weight_stable` is true | 422 `WEIGHT_UNSTABLE` **[P8]** |
| 3 | `weight_g` is above 0 | 422 `NO_ITEM` **[P23]** |
| 4 | `weight_g` is at or above `minimum_weight_g` | 422 `ZERO_WEIGHT` **[P23]** |
| 5 | `maximum_weight_g` is NULL, or `weight_g` is at or below it | 422 `OVER_MAX_WEIGHT` |

- **On success:** `status` = `ACCEPTED`. `points_preview` =
  `floor(weight_g × points_per_gram)`.
- **On failure:** `status` = `REJECTED`, `failure_code` = the error code,
  `closed_at` = now. No points. The student scans again to start over.

### 5.6 POST /device/sessions/{id}/complete

Reports the actuator result. This is the call that credits points.

```http
POST /api/v1/device/sessions/b3f1…/complete
{ "actuator_ok": true, "cycle_ms": 3200 }
→ 200 { "transaction_id": 1042, "points_awarded": 18, "balance": 240 }
```

| Field | Rule | Stored in |
|---|---|---|
| `actuator_ok` | Required. Boolean. True only when the limit switches confirm the transfer | `actuator_ok` |
| `cycle_ms` | Optional. Integer, 0 or more | `actuator_cycle_ms` |

**When `actuator_ok` is true,** in one database transaction:

1. Lock the session row and confirm it is still `ACCEPTED` **[P24]**.
2. Insert the `transactions` row: `transaction_code` **[P25]**, `session_id`,
   `reward_rule_id` = the rule the session opened under, `points_awarded` =
   `floor(net_weight_g × points_per_gram)`, `status` = `COMPLETED`.
3. Call `PointsService` to write the `EARN` ledger row for the session's user.
   Deposits never inserts into `point_ledger` itself.
4. Set the session to `COMPLETED` with `actuator_ok`, `actuator_cycle_ms` and
   `closed_at`.

After the commit, dispatch `DepositCompleted`. The reply carries
`transactions.id`, the points awarded, and the user's balance
(`SUM(point_ledger.points_delta)`).

**When `actuator_ok` is false:** the session becomes `FAILED` with `failure_code`
= `ACTUATOR_FAILED`, and no transaction or ledger row is written. The reply is
still 200, in the same shape **[P26]**:

```http
→ 200 { "transaction_id": null, "points_awarded": 0, "balance": 222 }
```

**Idempotency:** `transactions.session_id` is unique, so a session can produce
only one transaction. A repeated `complete` returns 200 with the stored result,
not an error. `complete` has no deadline: an `ACCEPTED` session accepts it
whenever it arrives.

### 5.7 POST /device/sessions/{id}/cancel

Cancels an open session. The bin's Cancel button calls it.

```http
POST /api/v1/device/sessions/b3f1…/cancel
→ 200 { "status": "CANCELLED" }
```

No body **[P27]**. Only an `OPEN` session can be cancelled. The session becomes
`CANCELLED` and `closed_at` is set.

---

## 6. Device Error Reference

| `code` | HTTP | `retryable` | Raised by | Effect on the session | Source |
|---|---|---|---|---|---|
| `INVALID_SIGNATURE` | 401 | false | Any signed route | None | Plan |
| `CLOCK_SKEW` | 401 | true, after a time sync | Any signed route | None | Plan |
| `DEVICE_INACTIVE` | 403 | false | Any signed route | None | Plan |
| `VALIDATION_ERROR` | 422 | false | Any route with a body | None | **[P8]** |
| `INVALID_QR` | 401 | false | `sessions` | Not created | Plan |
| `USER_INACTIVE` | 403 | false | `sessions` | Not created | Plan |
| `BIN_FULL` | 409 | false | `sessions` | Not created | Plan |
| `NO_REWARD_RULE` | 503 | false | `sessions` | Not created | Plan |
| `DAILY_LIMIT_REACHED` | 429 | false | `sessions` | Not created | Plan |
| `DUPLICATE_TRANSACTION` | 409 | false | `sessions` | Not created | Plan |
| `SESSION_NOT_FOUND` | 404 | false | `deposit`, `complete`, `cancel` | None | **[P8]** |
| `INVALID_SESSION_STATE` | 409 | false | `deposit`, `complete`, `cancel` | None | **[P8]** |
| `SESSION_EXPIRED` | 410 | false | `deposit`, `complete`, `cancel` | `EXPIRED` | Plan |
| `VERIFICATION_FAILED` | 422 | false | `deposit` | `REJECTED` | Plan |
| `WEIGHT_UNSTABLE` | 422 | false | `deposit` | `REJECTED` | **[P8]** |
| `NO_ITEM` | 422 | false | `deposit` | `REJECTED` | Plan |
| `ZERO_WEIGHT` | 422 | false | `deposit` | `REJECTED` | Plan |
| `OVER_MAX_WEIGHT` | 422 | false | `deposit` | `REJECTED` | Plan |
| `DB_UNAVAILABLE` | 503 | true | Any route except `time` | None | Plan |

- `VALIDATION_ERROR` means the body is not valid JSON or a field breaks its rule.
  It does not change the session, so the firmware can correct and resend.
- `DB_UNAVAILABLE` is returned when the database cannot be reached, including
  when the signature middleware cannot look up the device.
- An actuator failure is not an error reply. It is a 200 from `complete`
  (Section 5.6).
- `ACTUATOR_FAILED` appears only in `deposit_sessions.failure_code`, never as a
  reply code.

---

## 7. Web Routes

### 7.1 Conventions

The plan lists the pages (Section 5.6 of the plan) but not their URLs. Every
path, route name and page placement in this section is proposed **[P28]**. The
pages themselves and the rules behind them come from the plan.

- All web routes use the `web` middleware group: session cookie and CSRF token.
- Pages are Blade views. Forms are plain `method="POST"` forms with `@csrf`. A
  `PUT` route is reached with `@method('PUT')` inside the form.
- A successful form post redirects to a page with a flash message. A failed
  validation redirects back with the errors and the old input.
- A guest who opens a signed-in page is redirected to `/login`. A student who
  opens an `/admin` route gets 403.
- A user can only see their own transactions, redemptions and notifications.
- The Module column says which module's `routes/web.php` holds the route.

### 7.2 Guest routes

| Method | Path | Route name | Module | Purpose |
|---|---|---|---|---|
| GET | `/login` | `login` | Identity | Login form |
| POST | `/login` | — | Identity | Sign in with email and password |
| POST | `/logout` | `logout` | Identity | Sign out. Needs a signed-in user |
| GET | `/forgot-password` | `password.request` | Identity | Form asking for an email address |
| POST | `/forgot-password` | `password.email` | Identity | Send the reset link |
| GET | `/reset-password/{token}` | `password.reset` | Identity | Form to set a new password. Both emails link here |
| POST | `/reset-password` | `password.update` | Identity | Save the new password |

- The four password routes are the ones in Laravel's password reset
  documentation, with the same names. Keep the name `password.reset`: Laravel's
  reset email builds its link from that route.
- The set-password email for a new account and the forgot-password email both use
  Laravel's password broker and both lead to `/reset-password/{token}`.
- **Login** fails for an account whose `password` is NULL. It also fails for a
  user whose `status` is `inactive` **[P29]**. Both show the same message as a
  wrong password.
- Login is limited to 5 attempts a minute for each email and IP address pair
  **[P29]**. A successful login sets `users.last_login_at` **[P6]** and
  redirects an admin to `/admin` and a student to `/home` **[P29]**.
- `POST /forgot-password` shows the same message whether or not the email belongs
  to an account **[P30]**.
- The link lifetime is Laravel's `expire` setting for password resets in
  `config/auth.php`. Laravel's documentation shows 60 minutes (Section 10).

### 7.3 Signed-in routes

These are the pages the plan lists for students. They are open to any signed-in
user, because every user has a QR token and admins appear on the user
leaderboard **[P31]**.

| Method | Path | Route name | Module | Purpose |
|---|---|---|---|---|
| GET | `/` | — | Identity | Redirect to `/home` or `/admin` by role |
| GET | `/home` | `home` | Rewards | Points balance and recent transactions |
| GET | `/profile` | `profile.show` | Identity | Profile, organization info and QR code |
| POST | `/profile/qr` | `profile.qr.regenerate` | Identity | Replace the QR token |
| GET | `/transactions` | `transactions.index` | Deposits | Own deposit history |
| GET | `/leaderboard` | `leaderboard` | Reporting | User leaderboard |
| GET | `/rewards` | `rewards.index` | Rewards | Rewards catalogue |
| GET | `/rewards/{reward}` | `rewards.show` | Rewards | One reward, with the redeem form |
| POST | `/redemptions` | `redemptions.store` | Rewards | Redeem a reward |
| GET | `/redemptions` | `redemptions.index` | Rewards | Own redemptions |
| GET | `/notifications` | `notifications.index` | Notifications | Notification list |
| POST | `/notifications/{notification}/read` | `notifications.read` | Notifications | Mark one as read |

- **QR code:** the page shows `user_qr_credentials.token` as a QR image, rendered
  on the server as SVG so the page needs no script **[P32]**. Regenerating
  overwrites the row, so the old token stops working at once.
- **Leaderboard:** ranks every user by points earned: the sum of `EARN` rows minus
  the `REVERSAL` rows that link to a transaction. Redemption rows are ignored.
- **Rewards catalogue:** shows rewards whose `is_active` is true.
- **Mark as read** sets `notifications.read_at`.

### 7.4 Admin routes

All are under `/admin`, with the `auth` middleware and an admin role check, and
route names starting with `admin.`.

**Dashboard and reporting (Reporting)**

| Method | Path | Route name | Purpose |
|---|---|---|---|
| GET | `/admin` | `admin.dashboard` | Dashboard page |
| GET | `/admin/dashboard/summary` | `admin.dashboard.summary` | Live summary, JSON (Section 7.5) |
| GET | `/admin/leaderboard/organizations` | `admin.leaderboard.organizations` | Organization leaderboard |
| GET | `/admin/reports` | `admin.reports` | Reports (Section 10) |

The organization leaderboard counts students only: filter on `role = 'student'`
before grouping. An organization's score is the total points earned by the
students currently in it.

**Users and organizations (Identity)**

| Method | Path | Route name | Purpose |
|---|---|---|---|
| GET | `/admin/users` | `admin.users.index` | User list, showing which accounts are not activated |
| GET | `/admin/users/create` | `admin.users.create` | New user form |
| POST | `/admin/users` | `admin.users.store` | Create a user |
| GET | `/admin/users/{user}/edit` | `admin.users.edit` | Edit form |
| PUT | `/admin/users/{user}` | `admin.users.update` | Save changes |
| POST | `/admin/users/import` | `admin.users.import` | CSV import |
| POST | `/admin/users/{user}/password-link` | `admin.users.password-link` | Send or resend the set-password email |
| PUT | `/admin/users/{user}/password` | `admin.users.password` | Admin sets a password. The fallback when email can't be sent |
| GET | `/admin/organizations` | `admin.organizations.index` | Organization list |
| GET | `/admin/organizations/create` | `admin.organizations.create` | New organization form |
| POST | `/admin/organizations` | `admin.organizations.store` | Create |
| GET | `/admin/organizations/{organization}/edit` | `admin.organizations.edit` | Edit form |
| PUT | `/admin/organizations/{organization}` | `admin.organizations.update` | Save changes |

An account is not activated while `users.password` is NULL.

**Transactions and sessions (Deposits)**

| Method | Path | Route name | Purpose |
|---|---|---|---|
| GET | `/admin/transactions` | `admin.transactions.index` | Transaction list and total collected weight |
| GET | `/admin/transactions/{transaction}` | `admin.transactions.show` | One transaction with its session's weight, verification and actuator values |
| POST | `/admin/transactions/{transaction}/void` | `admin.transactions.void` | Void a transaction |
| GET | `/admin/sessions/review` | `admin.sessions.review` | `ACCEPTED` sessions older than the review threshold |

Total collected weight is the sum of `net_weight_g` over deposits whose
transaction is `COMPLETED`. Voided deposits are excluded.

**Bins (Devices)**

| Method | Path | Route name | Purpose |
|---|---|---|---|
| GET | `/admin/bins` | `admin.bins.index` | Bin list with current fill level |
| GET | `/admin/bins/create` | `admin.bins.create` | Register form |
| POST | `/admin/bins` | `admin.bins.store` | Register a bin and issue its secret |
| GET | `/admin/bins/{device}` | `admin.bins.show` | One bin and its fill history |
| GET | `/admin/bins/{device}/edit` | `admin.bins.edit` | Edit form, including calibration values |
| PUT | `/admin/bins/{device}` | `admin.bins.update` | Save changes |
| POST | `/admin/bins/{device}/secret` | `admin.bins.secret` | Issue a new secret |

**Rewards, redemptions and points rate (Rewards)**

| Method | Path | Route name | Purpose |
|---|---|---|---|
| GET | `/admin/rewards` | `admin.rewards.index` | Reward list |
| GET | `/admin/rewards/create` | `admin.rewards.create` | New reward form |
| POST | `/admin/rewards` | `admin.rewards.store` | Create |
| GET | `/admin/rewards/{reward}/edit` | `admin.rewards.edit` | Edit form |
| PUT | `/admin/rewards/{reward}` | `admin.rewards.update` | Save changes |
| GET | `/admin/redemptions` | `admin.redemptions.index` | Redemption list |
| POST | `/admin/redemptions/{redemption}/fulfil` | `admin.redemptions.fulfil` | Mark as fulfilled |
| POST | `/admin/redemptions/{redemption}/cancel` | `admin.redemptions.cancel` | Cancel a pending redemption |
| GET | `/admin/points-rate` | `admin.points-rate.show` | Current rule and past rules |
| POST | `/admin/points-rate` | `admin.points-rate.store` | Save a new rule |

**No delete routes** **[P33]**. Users, organizations, rewards and bins are
referenced by other tables. They are switched off with their `status` or
`is_active` column instead.

Admins read their notifications at `/notifications`, the same route students use.

### 7.5 Live summary

The dashboard's polling script calls this route every 10 seconds **[P34]**. It
uses the admin's normal session, not the device scheme.

```http
GET /admin/dashboard/summary
→ 200 {
    "generated_at": "2026-10-03T01:39:00Z",
    "bins": [
      { "id": 1, "name": "Bin 1", "fill_percent": 17.5, "status": "OK",
        "last_seen_at": "2026-10-03T01:38:42Z" }
    ],
    "today": { "deposits": 12, "weight_g": 2210.4, "points_awarded": 221 },
    "pending_redemptions": 3,
    "sessions_awaiting_review": 0
  }
```

**[P34]** for every field.

| Field | Source |
|---|---|
| `bins[]` | Each device that is not `retired`, with its latest `bin_telemetry` row. `fill_percent` and `status` are null when the bin has no reading |
| `today.deposits` | Count of `COMPLETED` transactions created since midnight, Asia/Manila |
| `today.weight_g` | Sum of `net_weight_g` for those transactions' sessions |
| `today.points_awarded` | Sum of `points_awarded` for those transactions |
| `pending_redemptions` | Count of redemptions whose `status` is `PENDING` |
| `sessions_awaiting_review` | Count of `ACCEPTED` sessions older than the review threshold |

Times in this reply are ISO 8601 in UTC. The script converts them for display.

### 7.6 Form rules

Lengths and required fields come from the schema. Other limits are **[P41]**.

**Create or edit a user**

| Field | Rule |
|---|---|
| `first_name`, `last_name` | Required, at most 100 characters each |
| `email` | Required, a valid email, at most 255 characters, unique |
| `student_number` | Optional, at most 50 characters, unique |
| `role` | `student` or `admin` |
| `organization_id` | Required when `role` is `student`. Must be NULL for an admin |
| `status` | `active` or `inactive` |

- Creating a user inserts the `users` row with `password` NULL and its
  `user_qr_credentials` row in one database transaction. The token is 40 random
  letters and digits **[P35]**.
- The set-password email is sent during the request. If the send fails, the user
  is still created and the admin is told the email was not sent.
- `PUT /admin/users/{user}/password` takes `password` and
  `password_confirmation`, at least 8 characters **[P41]**.

**CSV import** **[P36]**

- Columns, with a header row: `student_number`, `first_name`, `last_name`,
  `email`, `organization_code`.
- Every row becomes a `student`. `organization_code` must match an existing
  `organizations.code`.
- The file is validated as a whole. If any row is invalid, nothing is imported
  and the page lists the rows and their errors.
- The import sends no email. Set-password emails are sent afterwards from the
  user list. This keeps a large import inside one request with no queue, and
  inside the mail service's daily limit.

**Create or edit an organization**

| Field | Rule |
|---|---|
| `name` | Required, at most 150 characters |
| `code` | Required, at most 50 characters, unique |
| `description` | Optional, at most 255 characters |
| `is_active` | Boolean |

**Register or edit a bin**

| Field | Rule |
|---|---|
| `device_code` | Required, at most 50 characters, unique |
| `name` | Required, at most 100 characters |
| `location` | Optional, at most 255 characters |
| `empty_distance_mm` | Required, above 0. The fill formula divides by it |
| `fill_threshold_percent` | Required, 0 to 100. Defaults to 90 |
| `weight_offset`, `weight_scale_factor` | Optional. Saving new values sets `calibrated_at` |
| `status` | `active`, `inactive` or `retired` |

The server generates the secret: 32 random bytes written as 64 hex characters.
It is shown once, on the page that follows registration or a new-secret request,
for the admin to enter in the bin's setup portal **[P39]**. Issuing a new secret
stops the old one at once.

**Save a points rule**

| Field | Rule |
|---|---|
| `name` | Required, at most 100 characters |
| `points_per_gram` | Required, above 0 |
| `minimum_weight_g` | Required, and at least 1 ÷ `points_per_gram` |
| `maximum_weight_g` | Optional. Above `minimum_weight_g`, and no more than the hardware weight limit |
| `daily_points_cap` | Optional. Whole number, 1 or more |

- The hardware weight limit is not stored anywhere in the plan or the schema. It
  becomes a setting in `config/ewaste.php` **[P37]**. Its value is not set here
  (Section 10).
- Saving runs in one database transaction: set `effective_to` to now on the
  current rule, then insert the new rule with `effective_from` set to now. The
  new rule takes effect at once; rules dated in the future are not supported
  **[P38]**.

**Create or edit a reward**

| Field | Rule |
|---|---|
| `name` | Required, at most 150 characters |
| `description` | Optional |
| `points_cost` | Required. Whole number, 1 or more **[P41]** |
| `stock_quantity` | Required. Whole number, 0 or more |
| `is_active` | Boolean |

**Redeem a reward** (`POST /redemptions`)

| Field | Rule |
|---|---|
| `reward_id` | Required. An existing reward whose `is_active` is true |
| `quantity` | Required. Whole number, 1 or more |

In one database transaction, through `RedemptionService`:

1. Lock the user's row (`SELECT … FOR UPDATE`) and sum the ledger.
2. Refuse if the balance is below `rewards.points_cost` × `quantity`.
3. Decrement stock with `WHERE stock_quantity >= ?` and check the affected row
   count. Refuse if no row changed.
4. Insert the redemption: `redemption_code` **[P25]**, `points_cost` = the total
   cost, `status` = `PENDING`.
5. `PointsService` writes the `REDEEM` ledger row with a negative `points_delta`.

A refusal redirects back with a message and changes nothing.

**Fulfil or cancel a redemption**

- **Fulfil:** only while `PENDING`. Sets `status` = `FULFILLED` and
  `fulfilled_at`, then dispatches `RedemptionFulfilled`.
- **Cancel:** only while `PENDING`. In one database transaction the status becomes
  `CANCELLED`, the stock is returned, and `PointsService` writes a `REVERSAL` row
  that gives the points back. A `FULFILLED` redemption can't be cancelled.

**Void a transaction** (`POST /admin/transactions/{transaction}/void`)

| Field | Rule |
|---|---|
| `void_reason` | Required, at most 500 characters **[P41]** |

Only a `COMPLETED` transaction can be voided. In one database transaction: set
`status` = `VOIDED`, `void_reason`, `voided_by` and `voided_at`, and have
`PointsService` write a `REVERSAL` row with the opposite `points_delta`. The
unique `(transaction_id, entry_type)` constraint stops a second reversal. The
balance may go below zero.

### 7.7 Events and notifications

Modules dispatch events and never call Notifications directly. Listeners write the
`notifications` rows.

| Event | Dispatched by | Notification goes to | `type` **[P40]** |
|---|---|---|---|
| `DepositCompleted` | Deposits, after `complete` commits | The depositing user | `DEPOSIT_COMPLETED` |
| `RedemptionFulfilled` | Rewards, after an admin fulfils | The redeeming user | `REDEMPTION_FULFILLED` |
| `BinFull` | Devices, from telemetry (Section 5.3) | One row per admin user | `BIN_FULL` |

---

## 8. Checks Before Integration

These follow from the design above and are worth testing with the device
simulator before real hardware is used:

- Both signing test vectors in Section 3.4 pass on the firmware and the server.
- A `complete` sent twice credits points once and returns 200 both times.
- A `deposit` or `cancel` sent twice returns the recorded outcome.
- A second `POST /device/sessions` from the same bin cancels the first session
  and opens a new one.
- An `OPEN` session past `expires_at` returns `SESSION_EXPIRED`, with or without
  the sweep having run.
- An `ACCEPTED` session accepts `complete` after the review threshold has passed.
- Every error reply on a device route is JSON in the error shape, including a
  malformed body.

---

## 9. Proposals to Confirm

Each item is a detail the plan and schema do not state. The design above is
written as if each is accepted.

| ID | Topic | Proposal |
|---|---|---|
| P1 | Base URL | `API_BASE_URL` holds scheme, host and optional port. The firmware appends `/api/v1/device/...` |
| P2 | Always JSON | Request bodies are `application/json`. Device routes never reply with a redirect or HTML |
| P3 | Number and time formats | Decimals are JSON numbers. Device API times are Unix seconds in UTC |
| P4 | Signing inputs | Key is the secret string's bytes; path starts at `/api/v1/device` with no query string; an empty body signs as empty; lower-case hex output |
| P5 | Order of signature checks | Headers and device code, then signature, then timestamp, then device status |
| P6 | Bookkeeping columns | When `devices.last_seen_at`, `devices.firmware_version`, `user_qr_credentials.last_used_at` and `users.last_login_at` are written |
| P7 | `retryable` | True only for `DB_UNAVAILABLE` and `CLOCK_SKEW`. `Retry-After` is 5 seconds |
| P8 | New error codes | `VALIDATION_ERROR`, `SESSION_NOT_FOUND`, `INVALID_SESSION_STATE`, `WEIGHT_UNSTABLE` |
| P9 | Unreadable replies | The firmware treats them as a network failure |
| P10 | Replays | A repeated `deposit`, `complete` or `cancel` returns the recorded outcome. A replayed `complete` reports the current balance |
| P11 | `REJECTED`, `failure_code`, `closed_at` | A failed `deposit` makes the session `REJECTED`. `failure_code` is the error code, or `ACTUATOR_FAILED`. `closed_at` is set on every final state |
| P12 | Expiry at request time | A device call on an `OPEN` session past `expires_at` expires it without waiting for the sweep |
| P13 | Active-session rule | A new session on a device closes that device's earlier `OPEN` session as `CANCELLED`. A new session is refused only while the user has an unexpired `OPEN` session on another device. `ACCEPTED` sessions are not closed and do not block. The check runs last. Alternative not taken: refuse the new session until the earlier one times out |
| P14 | `/device/time` body | `{ "time": <Unix seconds> }` |
| P15 | `/device/config` body | The field names and null rules in Section 5.2 |
| P16 | When the bin calls | Config at boot and with each telemetry send. Telemetry at boot, after each session ends, and on an interval |
| P17 | Device field rules | The required, optional and range rules in Section 5 that are not column definitions |
| P18 | Telemetry reply | 201 with `fill_percent` and `status` |
| P19 | `BinFull` event | Dispatched only when a reading turns `FULL` after a reading that was not |
| P20 | Bin-full check | Latest `fill_percent` against the current threshold. No reading means not full |
| P21 | `display_name` | `users.first_name` |
| P22 | Deposit evidence | Weight and verification values are stored on rejected sessions as well |
| P23 | Weight codes | `NO_ITEM` for a weight of 0 or less; `ZERO_WEIGHT` for a weight above 0 but below the minimum. Both limits are inclusive |
| P24 | Row locks | `complete` locks the session row before writing |
| P25 | Code formats | `transaction_code` is `TXN-` + date as `YYYYMMDD` + `-` + 6 random upper-case letters and digits. `redemption_code` is the same with `RDM-`. The unique index catches a collision |
| P26 | Actuator failure reply | 200 with `transaction_id` null, `points_awarded` 0 and the current balance |
| P27 | `cancel` | No request body. Reply is `{ "status": "CANCELLED" }` |
| P28 | Web paths | Every web path, route name and page placement in Section 7 |
| P29 | Login | Inactive users can't sign in. 5 attempts a minute per email and IP address. Redirect by role |
| P30 | Forgot password | The same message whether or not the email has an account |
| P31 | Student pages | Open to any signed-in user, admins included |
| P32 | QR image | Rendered on the server as SVG |
| P33 | No delete routes | Users, organizations, rewards and bins are switched off, not deleted |
| P34 | Live summary | The fields in Section 7.5 and a 10-second polling interval |
| P35 | QR token | 40 random letters and digits |
| P36 | CSV import | The five columns; all rows or none; no email sent by the import |
| P37 | Hardware weight limit | A new setting in `config/ewaste.php`, used when a rule is saved |
| P38 | New rules | Take effect at once. No future-dated rules |
| P39 | Device secret | 64 hex characters, generated by the server, shown once |
| P40 | Notification types | `DEPOSIT_COMPLETED`, `REDEMPTION_FULFILLED`, `BIN_FULL` |
| P41 | Form limits not in the schema | Password at least 8 characters; `points_cost` at least 1; `void_reason` required |

---

## 10. Open Questions

These have no proposal. The plan and schema do not hold the information needed to
answer them.

1. **Sessions in review.** The plan lists an `ACCEPTED` session with no `complete`
   for admin review but defines no action. Section 7.4 gives a list page only.
   What should an admin be able to do with such a session?
2. **Reports.** The plan names a reports page and scheduled reports but does not
   say what they contain. This depends on what the project guidelines ask for.
3. **Own password and profile.** The plan covers setting a password by email and
   by an admin. Can a signed-in user change their own password or details? No
   route is designed for it.
4. **Link lifetime.** Laravel's documented setting for a reset link is 60 minutes.
   Is that long enough for the set-password email sent to a new account?
5. **Firmware timing.** The telemetry interval and the backoff between `complete`
   retries are not set.
6. **Bulk invitations.** After a CSV import, is a bulk "send set-password emails"
   action needed, or is sending from each user's row enough?
7. **Hardware weight limit.** The value for the new setting in P37.
8. **QR library.** Which Composer package renders the QR code, and whether it
   supports Laravel 13.
