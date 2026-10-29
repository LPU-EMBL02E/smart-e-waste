# Track B — Deposit path

Build spec for everything the bin calls: the device API, bins, the points rule,
the ledger and the deposit state machine. It runs in parallel with
[Track A](TRACK-A-ACCOUNTS.md) and is tested with `tools/simulator/` until the
hardware is ready.

The rules come from `docs/`, which wins over this sheet if the two ever disagree:

- `API_Design.md` §3 (signing, errors, retries), §4 (state machine), §5 (device
  endpoints), §6 (error reference), §7.4 (bins and points-rate pages), §7.6
  ("Register or edit a bin", "Save a points rule"), §7.7 (events), §8 (checks)
- `System_Plan.md` §4 (boundaries), §5.3 and §5.7 (points rules), §6.1 (locks)
- `Database_Schema.md` §4–§9

**Points and the ledger need the owner's sign-off** (AGENTS.md §11). This sheet
implements the rules exactly as `docs/` states them and adds none. Any question
the docs don't answer goes back to the owner, not into the code.

**Logic first, interface later.** The device API has no interface at all. The two
admin pages here are bare Blade; the design pass comes later.

---

## Module boundaries

| Module | Owns, and is the only writer of | Service |
|---|---|---|
| Devices | `devices`, `bin_telemetry` | `BinService` |
| Rewards | `reward_rules`, `point_ledger` | `RewardRuleService`, `PointsService` |
| Deposits | `deposit_sessions`, `transactions` | `DepositService` |
| Notifications | `notifications` | Listeners only |

Rules for this track:

- **Writes go through the owning module's service.** `DepositService` never
  inserts into `point_ledger`; it calls `PointsService` inside its own
  transaction. `PointsService` never opens a transaction; its caller has one.
- **Reads may cross modules.** `BinService::configFor()` reads the current rule
  through `RewardRuleService::current()`; `DepositService` reads it through
  `PointsService::ruleInEffectAt()`.
- **Modules announce, they don't call Notifications.** `BinService` dispatches
  `BinFull` and `BinFault`; `DepositService` dispatches `DepositCompleted`. All
  three are dispatched **after** the commit. Listeners are cheap: the queue is
  `sync`, so they run inside the device's request.
- **Device controllers are thin.** They read the device from
  `$request->attributes->get('device')` (set by `VerifyDeviceSignature`), call
  one service method and return JSON. Every error is a `DeviceApiException` with
  a `DeviceErrorCode`, so a device route never answers with HTML.
- **Lock first.** A status change locks its row with `lockForUpdate()` as the
  **first read** of its transaction (`API_Design.md` §4.1, P24). Opening a
  session locks the device row, then the user row, always in that order.
- **Write first, throw after.** A rejection or an expiry is a write that must
  stay. Commit it, then throw the `DeviceApiException` after `DB::transaction()`
  returns. Throwing inside the transaction would roll the write back.
- Status and code values are enums in `Support/`, never bare strings.

---

## Step B0 — Track B's test setup

Starts after Track A's step A0 (layouts and the Identity factories).

1. **Factories**, one folder per module, each model pointing at its factory
   through `newFactory()`:
   - `database/factories/Devices/` — `DeviceFactory` (active, calibrated, with a
     known secret; `inactive()` state), `BinTelemetryFactory`
   - `database/factories/Rewards/` — `RewardRuleFactory`. Values are throwaway
     test numbers only; the real point values need instructor approval and are
     entered on the points-rate page
   - `database/factories/Deposits/` — `DepositSessionFactory` with one state per
     status, `TransactionFactory`
2. **`tests/Concerns/SignsDeviceRequests.php`**, a trait with
   `deviceGet(Device $device, string $path)` and
   `devicePost(Device $device, string $path, array $body)`. It encodes the body
   once and signs those exact bytes with the canonical string from
   `API_Design.md` §3.2. Every device test uses it, so the signing code exists
   once.

---

## Step B1 — Device signature

`VerifyDeviceSignature` is written but untested. Finish
`tests/Feature/Device/DeviceSignatureTest.php`; never change a vector.

- `test_it_accepts_the_documented_get_vector`
- `test_it_accepts_the_documented_post_vector`
- `test_it_rejects_a_bad_signature`
- `test_it_gives_an_unknown_device_the_bad_signature_answer`
- `test_it_rejects_a_timestamp_outside_the_window_as_clock_skew`
- `test_it_rejects_an_inactive_device_only_after_the_signature_passes`
- `test_it_answers_time_without_a_signature`
- `test_it_answers_a_malformed_body_with_a_validation_error`
- `test_it_sets_last_seen_at`

---

## Step B2 — Telemetry, config and bin faults (Devices)

1. **`Devices/Support/BinFaultCode.php`**, a string enum: `ACTUATOR_JAM`,
   `ACTUATOR_TIMEOUT`, `ACTUATOR_RETRACT_FAILED`, `STATION_UNREACHABLE`,
   `CAMERA_UNAVAILABLE`, `SCANNER_UNAVAILABLE` (`Database_Schema.md` §5).
2. **`TelemetryRequest`** adds `panel_open` (required, boolean) and
   `fault_code` (present, nullable, `Rule::enum(BinFaultCode::class)`) (P42).
3. **`BinService::recordTelemetry(Device $device, float $distanceMm, bool $panelOpen, ?BinFaultCode $fault): BinTelemetry`**
   - `fill_percent` and `status` exactly as `API_Design.md` §5.3;
   - insert the row with `panel_open` and `fault_code`;
   - after the insert, dispatch `BinFull` on the change to `FULL` (P19) and
     `BinFault` when the fault is not null and differs from the previous
     reading's (P43). An open service panel dispatches nothing.
4. **`BinService::configFor(Device $device): array`** — the body in
   `API_Design.md` §5.2, with the null rules from P15. It answers even when no
   rule is in effect.
5. **`BinService::isFull(Device $device): bool`** — the latest reading against the
   current threshold; no reading means not full (P20).
6. **New event** `Devices/Events/BinFault.php`; **new listener**
   `Notifications/Listeners/SendBinFaultNotifications.php`; new
   `NotificationType::BinFault` (`BIN_FAULT`). Fill in
   `SendBinFullNotifications`. Both write one row per admin.
   Register `BinFault` in `ModuleServiceProvider::LISTENERS`, a file both tracks
   share: one small commit, announced.

Tests (`tests/Feature/Devices/TelemetryTest.php`, `ConfigTest.php`):
- `test_it_computes_fill_percent_and_clamps_it`
- `test_it_marks_a_reading_full_at_the_threshold`
- `test_it_stores_panel_open_and_the_fault_code`
- `test_it_rejects_an_unknown_fault_code`
- `test_it_notifies_admins_once_when_a_bin_turns_full`
- `test_it_notifies_admins_once_when_a_new_fault_appears`
- `test_it_does_not_notify_for_an_open_panel`
- `test_it_returns_config_with_nulls_when_no_rule_is_in_effect`
- `test_it_returns_the_calibration_values`

---

## Step B3 — Bin admin pages (Devices)

| Method | Does |
|---|---|
| `BinService::register(array $data): array` | Insert the device with a secret from `DeviceSecret::generate()`. Returns `[Device, string $secret]` so the page can show the secret once (P39) |
| `BinService::update(Device $device, array $data): Device` | New method. Saves the fields in `API_Design.md` §7.6; saving new calibration values sets `calibrated_at` |
| `BinService::rotateSecret(Device $device): string` | Replaces the secret; the old one stops at once |

`BinController` calls these. `index` lists bins with their latest reading,
eager-loaded. The pages sit behind `auth`, `verified` and `admin`; until Track A
has login, test them with `actingAs()` an admin from the user factory.

Tests (`tests/Feature/Devices/AdminBinTest.php`):
- `test_it_registers_a_bin_and_shows_the_secret_once`
- `test_it_rotates_the_secret_and_the_old_one_stops_working`
- `test_it_sets_calibrated_at_when_calibration_changes`
- `test_it_keeps_students_out_of_the_bin_pages`

---

## Step B4 — The points rule (Rewards)

- `RewardRuleService::save(array $data): RewardRule` — in one transaction, close
  the current rule (`effective_to` = now) and insert the new one
  (`effective_from` = now). No future-dated rules (P38).
- `RewardRuleService::current(): ?RewardRule`.
- `PointsRateController` shows the current rule and past rules, and saves
  through `StoreRewardRuleRequest`, which already holds the constraints between
  the variables.

Tests (`tests/Feature/Rewards/RewardRuleTest.php`):
- `test_it_closes_the_current_rule_when_a_new_one_is_saved`
- `test_it_never_leaves_two_rules_in_effect`
- `test_it_refuses_a_minimum_below_one_point`
- `test_it_refuses_a_maximum_not_above_the_minimum`

---

## Step B5 — The ledger (Rewards)

Only the methods the deposit path needs. Redemptions, voids and the leaderboard
methods are Track C.

| Method | Rule |
|---|---|
| `ruleInEffectAt(DateTimeInterface $at): ?RewardRule` | The rule with `effective_from` ≤ `$at` and `effective_to` NULL or later |
| `balanceFor(User $user): int` | `SUM(points_delta)` in the query, never in PHP |
| `pointsEarnedToday(User $user): int` | `points_awarded` of the user's transactions since midnight in `config('ewaste.display_timezone')`, through `deposit_sessions.user_id`. Voided ones still count |
| `isUnderDailyCap(User $user, RewardRule $rule): bool` | True when the cap is NULL or not yet reached |
| `creditDeposit(Transaction $transaction): PointLedgerEntry` | One `EARN` row for the session's user. Called inside `DepositService`'s transaction; the unique `(transaction_id, entry_type)` stops a second credit |

Tests (`tests/Feature/Rewards/PointsServiceTest.php`):
- `test_it_sums_the_balance_from_the_ledger`
- `test_it_counts_today_from_manila_midnight`
- `test_it_counts_voided_transactions_toward_the_cap`
- `test_it_treats_a_null_cap_as_no_cap`
- `test_it_credits_a_deposit_once`

---

## Step B6 — The deposit state machine (Deposits)

**Signature change.** The skeleton's `recordDeposit()`, `complete()` and
`cancel()` take a loaded `DepositSession`. That read would happen before the
lock, which `API_Design.md` §4.1 forbids. They take the device and the session
id instead, and read the session with `lockForUpdate()` as the first read inside
their transaction, scoped to the device:

```php
public function openSession(Device $device, string $qrToken, ?string $firmwareVersion): DepositSession
public function recordDeposit(Device $device, string $sessionId, array $payload): DepositSession
public function complete(Device $device, string $sessionId, bool $actuatorOk, ?int $cycleMs): DepositSession
public function cancel(Device $device, string $sessionId): DepositSession
public function expireOpenSessions(): int
```

A session that doesn't exist or belongs to another device is
`SESSION_NOT_FOUND` (P8).

| Method | Rules |
|---|---|
| `openSession` | First expire this device's and user's stale `OPEN` sessions with the conditional update (P12). Then, in one transaction, lock the device row, then the user row, and run the six checks of `API_Design.md` §5.4 in order. On success: cancel this device's earlier `OPEN` session, insert the new one, set `last_used_at` (§4.3) |
| `recordDeposit` | Store all six values either way (P22). Run the five checks of §5.5 against the rule the session opened under. Pass: `ACCEPTED`. Fail: `REJECTED` with `failure_code` and `closed_at`, committed, then the error |
| `complete` | `actuator_ok` true: insert the transaction (`ReferenceCode::transaction()`, the session's rule, `floor(net_weight_g × points_per_gram)`), `PointsService::creditDeposit()`, session `COMPLETED`; dispatch `DepositCompleted` after the commit. False: `FAILED` with `ACTUATOR_FAILED`, no transaction (P26). A repeat returns the recorded result (§4.2) |
| `cancel` | `OPEN` only, to `CANCELLED` with `closed_at` |
| `expireOpenSessions` | `WHERE status = 'OPEN' AND expires_at <= now`, a conditional update; never touches `ACCEPTED` |

Every call answers per state exactly as the table in `API_Design.md` §4.2,
including the replays that return a recorded outcome instead of an error.

`SessionController` fills in `store`, `deposit`, `complete` and `cancel`: read
the device from the request, call the service, and shape the replies in
`API_Design.md` §5.4–§5.7. `complete`'s `balance` comes from
`PointsService::balanceFor()` at reply time.

Fill in `SendDepositCompletedNotification`: one row for the depositing user.

Tests (`tests/Feature/Deposits/`, one file per call, all through the signing
trait):
- `test_it_opens_a_session_from_a_valid_token`
- `test_it_refuses_each_open_check_in_order` (one test per code:
  `INVALID_QR`, `USER_INACTIVE`, `BIN_FULL`, `NO_REWARD_RULE`,
  `DAILY_LIMIT_REACHED`, `DUPLICATE_TRANSACTION`)
- `test_it_cancels_the_bins_earlier_open_session`
- `test_it_leaves_an_accepted_session_alone_on_a_new_scan`
- `test_it_accepts_a_valid_deposit_with_a_points_preview`
- `test_it_rejects_each_deposit_check_and_keeps_the_evidence` (one per code)
- `test_it_expires_an_open_session_at_request_time`
- `test_it_credits_points_on_complete`
- `test_it_credits_points_once_when_complete_is_repeated`
- `test_it_fails_the_session_without_points_when_the_actuator_fails`
- `test_it_accepts_complete_after_the_review_threshold`
- `test_it_answers_every_state_as_the_state_table` (data-driven over §4.2)
- `test_it_hides_another_devices_session`
- `test_it_uses_the_rule_the_session_opened_under`
- `test_it_expires_only_open_sessions_in_the_sweep`

---

## Step B7 — End to end with the simulator

1. `tools/simulator/simulate.py`: the telemetry call sends `panel_open` and
   `fault_code` (a `tools/` change, standard library only).
2. Run every scenario in `tools/simulator/README.md` against
   `php artisan serve` with a registered bin. All pass, with exit code 0.
3. Check the database after `replay-complete`: one transaction and one `EARN`
   row (`API_Design.md` §8).

---

## Views

Bare Blade from `resources/views/README.md`: `devices/admin/bins/*` (including
`secret`, the page that shows a new secret once) and
`rewards/admin/points-rate/show`. Plain forms and tables, no styling, no
JavaScript.

## Done when

- Every step's tests pass, plus `php artisan test`, `./vendor/bin/pint --test`,
  `../scripts/check-swappability.sh` and
  `python3 ../tools/simulator/test_vectors.py`.
- Every simulator scenario passes against a local server.
- No controller contains a query or a rule; `point_ledger` is written only by
  `PointsService`; every device error is JSON in the error shape.
