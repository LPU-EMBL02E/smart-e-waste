# EMBL02E Smart E-Waste Collection and Monitoring System — Database Schema

## Conventions

- **Engine and character set:** InnoDB, `utf8mb4`, collation `utf8mb4_unicode_ci`.
  Set explicitly; MariaDB 11.8's default collation does not import into MySQL.
- **Time:** every timestamp is stored in UTC. Set `'timezone' => '+00:00'` on the
  Laravel database connection so this holds on any host, and convert to
  Asia/Manila only when displaying.
- **Status and type columns** are VARCHAR, validated in the application, with the
  allowed values listed under each table.
- **Defined by Laravel migrations.** This file documents them; the migrations are
  what actually builds the schema.

## 1. organizations
- `id` — BIGINT UNSIGNED — **PK**
- `name` — VARCHAR(150) — NOT NULL
- `code` — VARCHAR(50) — UNIQUE, NOT NULL
- `description` — VARCHAR(255) — NULL
- `is_active` — BOOLEAN — NOT NULL, DEFAULT TRUE
- `created_at` — TIMESTAMP — NOT NULL
- `updated_at` — TIMESTAMP — NOT NULL

## 2. users
- `id` — BIGINT UNSIGNED — **PK**
- `organization_id` — BIGINT UNSIGNED — **FK → organizations.id**, NULL — required for students (validated in the application), NULL for admins
- `role` — VARCHAR(20) — NOT NULL, DEFAULT `student` — values: `student`, `admin`
- `student_number` — VARCHAR(50) — UNIQUE, NULL
- `email` — VARCHAR(255) — UNIQUE, NOT NULL
- `password` — VARCHAR(255) — NULL — NULL until the student sets a password from the emailed link; login fails while it is NULL
- `first_name` — VARCHAR(100) — NOT NULL
- `last_name` — VARCHAR(100) — NOT NULL
- `status` — VARCHAR(20) — NOT NULL, DEFAULT `active` — values: `active`, `inactive`
- `last_login_at` — TIMESTAMP — NULL
- `created_at` — TIMESTAMP — NOT NULL
- `updated_at` — TIMESTAMP — NOT NULL

## 3. user_qr_credentials
*(one row per user, holding the current token)*
- `id` — BIGINT UNSIGNED — **PK**
- `user_id` — BIGINT UNSIGNED — **FK → users.id**, UNIQUE, NOT NULL
- `token` — VARCHAR(64) — UNIQUE, NOT NULL — random, URL-safe; this is the string the QR code encodes
- `last_used_at` — TIMESTAMP — NULL
- `created_at` — TIMESTAMP — NOT NULL
- `updated_at` — TIMESTAMP — NOT NULL — also the time the token was last regenerated

Regenerating a token overwrites the row, so the old token stops matching
immediately and no revocation flag is needed.

The row is created together with the user account. A scan of the token only
identifies the user for one deposit; it can't spend points or open the account.

## 4. devices
*(one physical unit: the bin and the ESP32 built into it)*
- `id` — BIGINT UNSIGNED — **PK**
- `device_code` — VARCHAR(50) — UNIQUE, NOT NULL — sent as the `X-Device-Code` header
- `name` — VARCHAR(100) — NOT NULL — the bin's display name
- `location` — VARCHAR(255) — NULL
- `secret` — TEXT — NOT NULL — HMAC signing secret, stored with Laravel's `encrypted` cast
- `firmware_version` — VARCHAR(30) — NULL
- `status` — VARCHAR(20) — NOT NULL, DEFAULT `active` — values: `active`, `inactive`, `retired`
- `last_seen_at` — TIMESTAMP — NULL
- `empty_distance_mm` — DECIMAL(10,2) — NOT NULL — ultrasonic reading when the bin is empty
- `fill_threshold_percent` — DECIMAL(5,2) — NOT NULL, DEFAULT 90.00
- `weight_offset` — DECIMAL(12,4) — NULL — current load cell calibration
- `weight_scale_factor` — DECIMAL(12,6) — NULL — current load cell calibration
- `calibrated_at` — TIMESTAMP — NULL
- `created_at` — TIMESTAMP — NOT NULL
- `updated_at` — TIMESTAMP — NOT NULL

`secret` is encrypted with the application's `APP_KEY`. If the app moves to a host
with a different key, the secrets become unreadable and every bin must be issued a
new one.

The calibration values are entered on the admin device page and returned to the
ESP32 by `GET /device/config`.

Only a device whose `status` is `active` is accepted by the device API.

## 5. bin_telemetry
- `id` — BIGINT UNSIGNED — **PK**
- `device_id` — BIGINT UNSIGNED — **FK → devices.id**, NOT NULL
- `distance_mm` — DECIMAL(10,2) — NOT NULL — raw reading from the device
- `fill_percent` — DECIMAL(5,2) — NOT NULL — computed by the server on insert
- `status` — VARCHAR(20) — NOT NULL
- `created_at` — TIMESTAMP — NOT NULL — when the reading was taken

`fill_percent` = `(empty_distance_mm − distance_mm) / empty_distance_mm × 100`,
clamped to 0–100.

Recommended `status` values: `OK`, `FULL` (`fill_percent` ≥
`devices.fill_threshold_percent`).

## 6. deposit_sessions
- `id` — CHAR(36) — **PK**
- `user_id` — BIGINT UNSIGNED — **FK → users.id**, NOT NULL
- `device_id` — BIGINT UNSIGNED — **FK → devices.id**, NOT NULL
- `status` — VARCHAR(20) — NOT NULL
- `expires_at` — TIMESTAMP — NOT NULL
- `closed_at` — TIMESTAMP — NULL
- `failure_code` — VARCHAR(50) — NULL
- `net_weight_g` — DECIMAL(10,2) — NULL
- `sample_count` — INT UNSIGNED — NULL
- `is_weight_stable` — BOOLEAN — NULL
- `verification_method` — VARCHAR(50) — NULL
- `verification_passed` — BOOLEAN — NULL
- `verification_score` — DECIMAL(5,4) — NULL
- `actuator_ok` — BOOLEAN — NULL
- `actuator_cycle_ms` — INT UNSIGNED — NULL
- `created_at` — TIMESTAMP — NOT NULL
- `updated_at` — TIMESTAMP — NOT NULL

`created_at` is when the session opened.

`status` values: `OPEN`, `ACCEPTED`, `COMPLETED`, `REJECTED`, `FAILED`, `EXPIRED`,
`CANCELLED`.

Only `OPEN` sessions are moved to `EXPIRED` by the timeout sweep. An `ACCEPTED`
session means the item may already be in the bin, so it waits for the device's
`complete` call and is listed for admin review if that never arrives.

## 7. reward_rules
- `id` — BIGINT UNSIGNED — **PK**
- `name` — VARCHAR(100) — NOT NULL
- `points_per_gram` — DECIMAL(10,4) — NOT NULL
- `minimum_weight_g` — DECIMAL(10,2) — NOT NULL
- `maximum_weight_g` — DECIMAL(10,2) — NULL — NULL means no upper limit
- `daily_points_cap` — INT UNSIGNED — NULL — most points one student can earn in a day; NULL means no cap
- `effective_from` — TIMESTAMP — NOT NULL
- `effective_to` — TIMESTAMP — NULL — NULL means still in effect
- `created_at` — TIMESTAMP — NOT NULL
- `updated_at` — TIMESTAMP — NOT NULL

The current rule is the row where `effective_from` ≤ now and `effective_to` is
NULL or later than now. Changing the rate sets `effective_to` on the current row
and inserts a new one, so past transactions keep the rule they were priced with.

No values are fixed here. The application checks, when a rule is saved, that
`minimum_weight_g` ≥ 1 ÷ `points_per_gram` and that `maximum_weight_g` >
`minimum_weight_g`. The mechanism is described in `System_Plan.md`, Section 5.7.

A session uses the rule that was in effect when it opened. At least one rule must
be in effect for the bin to accept deposits; the application also makes sure rules
never overlap, since the database can't enforce that.

## 8. transactions
- `id` — BIGINT UNSIGNED — **PK**
- `transaction_code` — VARCHAR(50) — UNIQUE, NOT NULL
- `session_id` — CHAR(36) — **FK → deposit_sessions.id**, UNIQUE, NOT NULL
- `reward_rule_id` — BIGINT UNSIGNED — **FK → reward_rules.id**, NOT NULL — the rule in effect when the session opened
- `points_awarded` — INT UNSIGNED — NOT NULL, DEFAULT 0
- `status` — VARCHAR(20) — NOT NULL, DEFAULT `COMPLETED` — values: `COMPLETED`, `VOIDED`
- `void_reason` — VARCHAR(500) — NULL
- `voided_by` — BIGINT UNSIGNED — **FK → users.id**, NULL
- `voided_at` — TIMESTAMP — NULL
- `created_at` — TIMESTAMP — NOT NULL — when the deposit completed
- `updated_at` — TIMESTAMP — NOT NULL

The UNIQUE `session_id` is what makes the device's `complete` call idempotent: a
retry finds the existing row and gets the original response.

`points_awarded` = `floor(net_weight_g × points_per_gram)`, computed by the server.

## 9. point_ledger
*(append-only: rows are never updated or deleted)*
- `id` — BIGINT UNSIGNED — **PK**
- `user_id` — BIGINT UNSIGNED — **FK → users.id**, NOT NULL
- `transaction_id` — BIGINT UNSIGNED — **FK → transactions.id**, NULL
- `redemption_id` — BIGINT UNSIGNED — **FK → redemptions.id**, NULL
- `entry_type` — VARCHAR(20) — NOT NULL
- `points_delta` — INT — NOT NULL
- `description` — VARCHAR(255) — NULL
- `created_at` — TIMESTAMP — NOT NULL

Unique constraints: `(transaction_id, entry_type)` and
`(redemption_id, entry_type)`.

| `entry_type` | Written when | Links to | `points_delta` |
|---|---|---|---|
| `EARN` | A deposit completes | `transaction_id` | Positive |
| `REDEEM` | A reward is redeemed | `redemption_id` | Negative |
| `REVERSAL` | A transaction is voided, or a redemption is cancelled | Either one | Opposite of the original |
| `ADJUSTMENT` | Reserved for manual corrections; no page writes it yet | Neither | Either sign |

A user's balance is `SUM(points_delta)`. There is no balance column. A balance can
go below zero if a transaction is voided after its points were spent.

Leaderboards rank by points earned, not by balance: the `EARN` rows minus the
`REVERSAL` rows that link to a transaction. Redemption rows are ignored.

## 10. rewards
- `id` — BIGINT UNSIGNED — **PK**
- `name` — VARCHAR(150) — NOT NULL
- `description` — TEXT — NULL
- `points_cost` — INT UNSIGNED — NOT NULL
- `stock_quantity` — INT UNSIGNED — NOT NULL, DEFAULT 0
- `is_active` — BOOLEAN — NOT NULL, DEFAULT TRUE
- `created_at` — TIMESTAMP — NOT NULL
- `updated_at` — TIMESTAMP — NOT NULL

## 11. redemptions
- `id` — BIGINT UNSIGNED — **PK**
- `redemption_code` — VARCHAR(50) — UNIQUE, NOT NULL
- `user_id` — BIGINT UNSIGNED — **FK → users.id**, NOT NULL
- `reward_id` — BIGINT UNSIGNED — **FK → rewards.id**, NOT NULL
- `quantity` — INT UNSIGNED — NOT NULL, DEFAULT 1
- `points_cost` — INT UNSIGNED — NOT NULL — total cost at the time of redemption
- `status` — VARCHAR(20) — NOT NULL, DEFAULT `PENDING` — values: `PENDING`, `FULFILLED`, `CANCELLED`
- `fulfilled_at` — TIMESTAMP — NULL
- `created_at` — TIMESTAMP — NOT NULL — when the reward was redeemed
- `updated_at` — TIMESTAMP — NOT NULL

An admin can cancel a redemption while it is `PENDING`. Cancelling returns the
stock and writes a `REVERSAL` row. A `FULFILLED` redemption can't be cancelled.

## 12. notifications
- `id` — BIGINT UNSIGNED — **PK**
- `user_id` — BIGINT UNSIGNED — **FK → users.id**, NOT NULL
- `type` — VARCHAR(50) — NOT NULL
- `title` — VARCHAR(150) — NOT NULL
- `message` — TEXT — NOT NULL
- `read_at` — TIMESTAMP — NULL
- `created_at` — TIMESTAMP — NOT NULL
- `updated_at` — TIMESTAMP — NOT NULL

A bin-full alert is one row per admin user.

## Foreign Key Summary

- `users.organization_id` → `organizations.id` (optional)
- `user_qr_credentials.user_id` → `users.id` (one-to-one)
- `bin_telemetry.device_id` → `devices.id`
- `deposit_sessions.user_id` → `users.id`
- `deposit_sessions.device_id` → `devices.id`
- `transactions.session_id` → `deposit_sessions.id` (one-to-one)
- `transactions.reward_rule_id` → `reward_rules.id`
- `transactions.voided_by` → `users.id` (optional)
- `point_ledger.user_id` → `users.id`
- `point_ledger.transaction_id` → `transactions.id` (optional)
- `point_ledger.redemption_id` → `redemptions.id` (optional)
- `redemptions.user_id` → `users.id`
- `redemptions.reward_id` → `rewards.id`
- `notifications.user_id` → `users.id`

## Unique Constraint Summary

- `organizations.code`
- `users.email`, `users.student_number`
- `user_qr_credentials.user_id`, `user_qr_credentials.token`
- `devices.device_code`
- `transactions.transaction_code`, `transactions.session_id`
- `point_ledger (transaction_id, entry_type)`, `point_ledger (redemption_id, entry_type)`
- `redemptions.redemption_code`

## Additional Indexes

Foreign keys and unique columns are indexed automatically. Add these two:

- `bin_telemetry (device_id, created_at)` — latest reading per bin, and fill history
- `deposit_sessions (status, expires_at)` — the timeout sweep, and the active-session check

## Primary Key Summary

Every table uses `id` as its primary key, except `deposit_sessions`, which uses a
UUID stored in `id`.

## Total Tables

**12 application tables.** Laravel also creates its own framework tables
(`migrations`, `sessions`, `cache`, `jobs`, `password_reset_tokens` and a few
related ones), so the database will show more than 12. Those are not part of this
design. `password_reset_tokens` holds the links for the set-password and
forgot-password emails.
