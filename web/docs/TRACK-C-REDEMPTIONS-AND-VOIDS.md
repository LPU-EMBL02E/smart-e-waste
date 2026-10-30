# Track C — Redemptions and voids

Build spec for the rest of the ledger: the reward catalogue, redeeming,
fulfilling and cancelling redemptions, and voiding a transaction. It follows
[Track B](TRACK-B-DEPOSIT-PATH.md), which wrote the `EARN` side of the ledger,
and runs in parallel with [Track A](TRACK-A-ACCOUNTS.md) and
[Track D](TRACK-D-REPORTING-AND-HISTORY.md).

The rules come from `docs/`, which wins over this sheet if the two ever disagree:

- `System_Plan.md` §4 (boundaries), §5.7 rule 7 (append-only ledger), §6.1
  (ledger and concurrency rules)
- `API_Design.md` §7.1 (conventions), §7.3–§7.4 (routes), §7.6 ("Create or edit
  a reward", "Redeem a reward", "Fulfil or cancel a redemption", "Void a
  transaction"), §7.7 (events), P24, P25, P33, P41
- `Database_Schema.md` §8–§11

**Points and the ledger need the owner's sign-off** (AGENTS.md §11). Every step
here writes `point_ledger` or moves points. This sheet implements the rules
exactly as `docs/` states them and adds none. Any question the docs don't answer
is listed under "Questions for the owner" and goes back to the owner, not into
the code.

**Logic first, interface later.** Every page here is bare Blade; the design pass
comes later.

---

## Module boundaries

| Module | Owns, and is the only writer of | Service |
|---|---|---|
| Rewards | `rewards`, `redemptions`, `point_ledger` | `RewardService` (new), `RedemptionService`, `PointsService` |
| Deposits | `transactions` | `DepositService` |
| Notifications | `notifications` | Listeners only |

Rules for this track:

- **Only `PointsService` writes `point_ledger`.** `RedemptionService` and
  `DepositService::void()` call it inside their own transaction. `PointsService`
  never opens a transaction.
- **Reads may cross modules.** `RedemptionService` locks and reads the user's row
  (Identity); it never writes it.
- **Lock first.** Every status change locks its row with `lockForUpdate()` as the
  **first read** of its transaction and checks the current status before
  writing (`System_Plan.md` §6.1, P24). A redemption locks the user's row first,
  before the balance is summed or the reward is read.
- **Refuse without writing.** A refusal (balance too low, out of stock, wrong
  status) changes nothing. The service throws, the transaction rolls back, and
  the controller redirects back with the message (`API_Design.md` §7.1).
- **Modules announce, they don't call Notifications.** `RedemptionService`
  dispatches `RedemptionFulfilled` **after** the commit. Cancelling and voiding
  dispatch nothing; `docs/` defines no notification for them.
- **No delete routes** (P33). A reward is switched off with `is_active`.
- Status values come from the existing `Redemption::STATUS_*` and
  `Transaction::STATUS_*` constants and `LedgerEntryType`; never bare strings.
- Every list is paginated and eager-loads what it shows (AGENTS.md §5.1).
- **A user sees only their own redemptions** (`API_Design.md` §7.1): the
  student list is read through `$request->user()`, never by id.

---

## Step C0 — Track C's test setup

1. **Factories** in `database/factories/Rewards/`, each model pointing at its
   factory through `newFactory()`:
   - `RewardFactory` — active, in stock; `inactive()` and `outOfStock()` states.
     Costs are throwaway test numbers; the real ones need instructor approval
     (`System_Plan.md` §10)
   - `RedemptionFactory` — one state per status
   - `PointLedgerEntryFactory` — for giving a test user a balance. An `earn()`
     state links a transaction from Track B's `TransactionFactory`
2. **A refusal exception per module**, each a `RuntimeException` carrying a
   message the page can show: `Rewards/Support/RedemptionRefused` for steps C3
   and C4, and `Deposits/Support/TransactionNotVoidable` for step C5. Deposits
   gets its own rather than reaching into Rewards.

---

## Step C1 — The rest of `PointsService` (Rewards)

| Method | Rule |
|---|---|
| `pointsEarnedBy(User $user): int` | `SUM(points_delta)` of the user's `EARN` rows and the `REVERSAL` rows that link to a transaction. `REDEEM` rows and redemption reversals are ignored (`Database_Schema.md` §9). In the query, never in PHP |
| `debitRedemption(Redemption $redemption): PointLedgerEntry` | One `REDEEM` row: `redemption_id`, `points_delta` = −`points_cost` (the total) |
| `reverseTransaction(Transaction $transaction, string $reason): PointLedgerEntry` | One `REVERSAL` row for the session's user: `transaction_id`, `points_delta` = −`points_awarded`, `description` = the reason |
| `reverseRedemption(Redemption $redemption, string $reason): PointLedgerEntry` | One `REVERSAL` row: `redemption_id`, `points_delta` = +`points_cost` |

Put the "points earned" filter in **one** place, a scope on
`PointLedgerEntry` (`scopeCountsAsEarned`). `pointsEarnedBy()` uses it, and so do
Track D's leaderboards, so the rule exists once.

The unique `(transaction_id, entry_type)` and `(redemption_id, entry_type)`
indexes stop a second reversal; let the exception propagate.

Tests (add to `tests/Feature/Rewards/PointsServiceTest.php`):
- `test_it_counts_earn_minus_transaction_reversals_as_points_earned`
- `test_it_ignores_redemptions_in_points_earned`
- `test_it_debits_the_total_cost_of_a_redemption`
- `test_it_reverses_a_transaction_with_the_opposite_delta`
- `test_it_refuses_a_second_reversal_of_the_same_transaction`
- `test_it_gives_the_points_back_when_a_redemption_is_reversed`

---

## Step C2 — The reward catalogue (Rewards)

| Method | Does |
|---|---|
| `RewardService::create(array $data): Reward` | Insert. Validation is `StoreRewardRequest` (exists) |
| `RewardService::update(Reward $reward, array $data): Reward` | Save. A price change never touches past redemptions, which store their own cost |
| `RewardService::list(): LengthAwarePaginator` | Every reward, for the admin list |
| `RewardService::catalogue(): LengthAwarePaginator` | `Reward::active()`, for students |

`Admin\RewardController` (index, create, store, edit, update) and
`RewardController` (index, show) call these. `show` displays one reward with the
redeem form.

Tests (`tests/Feature/Rewards/RewardCatalogueTest.php`):
- `test_it_creates_and_edits_a_reward`
- `test_it_refuses_a_cost_below_one_point`
- `test_it_shows_students_only_active_rewards`
- `test_it_keeps_students_out_of_the_admin_reward_pages`

---

## Step C3 — Redeeming (Rewards)

`RedemptionService::redeem(User $user, Reward $reward, int $quantity): Redemption`,
in one transaction, exactly as `API_Design.md` §7.6:

1. Lock the user's row (`User::whereKey(...)->lockForUpdate()->first()`) as the
   first read, then sum the ledger with `PointsService::balanceFor()`.
2. Re-read the reward inside the transaction, after the lock, for its current
   `points_cost`. Refuse if the balance is below `points_cost` × `quantity`.
3. Decrement stock with a guarded update,
   `Reward::whereKey($id)->where('stock_quantity', '>=', $quantity)->decrement(...)`,
   and refuse if it changed no row.
4. Insert the redemption: `ReferenceCode::redemption()` (P25), `points_cost` =
   the total, `status` = `PENDING`.
5. `PointsService::debitRedemption()`.

The balance and stock are **not** checked in `StoreRedemptionRequest`: a check
outside the lock is a race. A balance below zero after a void simply fails
step 2 (`System_Plan.md` §6.1).

`RedemptionController::store` calls it and redirects to `redemptions.index`
with a flash message, or back with the refusal. `index` lists the user's own
redemptions, newest first, with the reward eager-loaded.

Tests (`tests/Feature/Rewards/RedeemTest.php`):
- `test_it_redeems_and_writes_a_redeem_row_for_the_total`
- `test_it_decrements_stock_by_the_quantity`
- `test_it_refuses_when_the_balance_is_too_low_and_changes_nothing`
- `test_it_refuses_when_stock_is_short_and_changes_nothing`
- `test_it_refuses_a_user_whose_balance_is_negative`
- `test_it_refuses_an_inactive_reward`
- `test_it_charges_the_price_at_redemption_time`
- `test_it_lists_only_the_users_own_redemptions`

The lock itself can't be raced on SQLite. Test the lock order by reading the
code in review, and test it for real on MariaDB once (Step C6).

---

## Step C4 — Fulfilling and cancelling (Rewards)

Both lock the redemption row as the first read and refuse unless it is still
`PENDING` (P24), so a redemption can't be both fulfilled and cancelled.

| Method | Rules |
|---|---|
| `fulfil(Redemption $redemption): Redemption` | `status` = `FULFILLED`, `fulfilled_at` = now. Dispatch `RedemptionFulfilled` after the commit |
| `cancel(Redemption $redemption, string $reason): Redemption` | `status` = `CANCELLED`, return the stock (`increment` by `quantity`), `PointsService::reverseRedemption()` |

Since the lock must be the first read, these take the redemption id, or re-read
the bound model with `lockForUpdate()` inside the transaction; never act on the
route-bound copy.

`Admin\RedemptionController`: `index` lists all redemptions, newest first,
paginated, with user and reward eager-loaded; `fulfil` and `cancel` call the
service and redirect back.

Fill in `SendRedemptionFulfilledNotification`: one row for the redeeming user,
type `REDEMPTION_FULFILLED`, wrapped in `rescue()` like
`SendDepositCompletedNotification`.

Tests (`tests/Feature/Rewards/AdminRedemptionTest.php`):
- `test_it_fulfils_a_pending_redemption_and_notifies_the_student`
- `test_it_cancels_a_pending_redemption_and_returns_points_and_stock`
- `test_it_refuses_to_cancel_a_fulfilled_redemption`
- `test_it_refuses_to_fulfil_a_cancelled_redemption`
- `test_it_dispatches_nothing_on_cancel`
- `test_it_keeps_students_out_of_the_admin_redemption_pages`

---

## Step C5 — Voiding a transaction (Deposits)

`DepositService::void(Transaction $transaction, User $admin, string $reason): Transaction`,
in one transaction, exactly as `API_Design.md` §7.6:

1. Lock the transaction row as the first read; refuse unless it is still
   `COMPLETED`.
2. Set `status` = `VOIDED`, `void_reason`, `voided_by` = the admin, `voided_at`
   = now.
3. `PointsService::reverseTransaction()` with the reason.

The balance may go below zero. A voided deposit still counts toward that day's
cap (Track B's `pointsEarnedToday()` already does this) and drops out of points
earned and total collected weight.

`Admin\TransactionController` (all three actions live here, so one person owns
the file):

| Action | Shows |
|---|---|
| `index` | Transactions, newest first, paginated, with session, user and device eager-loaded; and total collected weight |
| `show` | One transaction with its session's weight, verification and actuator values, and the void form while it is `COMPLETED` |
| `void` | `VoidTransactionRequest` (exists), the service, redirect back |

**Total collected weight** is the sum of `net_weight_g` over sessions whose
transaction is `COMPLETED` (`API_Design.md` §7.4). Write it once, as
`DepositService::totalCollectedWeightGrams()`, built on a new
`Transaction::scopeCompleted()`. Remove the duplicate stub
`ReportingQueries::totalCollectedWeightGrams()` in the same commit; nothing else
calls it.

Tests (`tests/Feature/Deposits/VoidTest.php`, `AdminTransactionTest.php`):
- `test_it_voids_a_completed_transaction_and_reverses_its_points`
- `test_it_allows_the_balance_to_go_below_zero`
- `test_it_refuses_to_void_a_transaction_twice`
- `test_it_requires_a_void_reason`
- `test_it_still_counts_a_voided_deposit_toward_the_daily_cap`
- `test_it_excludes_voided_deposits_from_total_collected_weight`
- `test_it_shows_the_session_values_on_the_transaction_page`
- `test_it_keeps_students_out_of_the_admin_transaction_pages`

---

## Step C6 — One check on MariaDB

SQLite can't show a lost lock. Once, on the Pi or a local MariaDB, run two
redemptions for the same user at the same moment (two terminals, or a short
script) whose total is above the balance, and check that exactly one succeeds.
Do the same for fulfil against cancel on one redemption. Record the result in
the PR.

---

## Questions for the owner

Ask before building the part each one affects. Do not pick an answer in code.

1. **Cancel reason.** `RedemptionService::cancel()` takes a reason, which
   becomes the `REVERSAL` row's description, but `API_Design.md` §7.6 defines no
   form field for it. Add a required `cancel_reason` field, or use a fixed
   description such as "Redemption RDM-… cancelled"?
2. **An inactive reward's page.** The catalogue lists active rewards only. Should
   `/rewards/{reward}` for an inactive reward be 404, or show the reward without
   the redeem form?

## Views

Bare Blade from `resources/views/README.md`: `rewards/admin/rewards/*`,
`rewards/rewards/{index,show}`, `rewards/redemptions/index`,
`rewards/admin/redemptions/index` and `deposits/admin/transactions/{index,show}`.
Plain forms and tables, no styling, no JavaScript. Times are converted to
`config('ewaste.display_timezone')` in the view.

## Done when

- Every step's tests pass, plus `php artisan test`, `./vendor/bin/pint --test`,
  `../scripts/check-swappability.sh` and
  `python3 ../tools/simulator/test_vectors.py`.
- The MariaDB check in Step C6 is recorded in the PR.
- `point_ledger` is written only by `PointsService`, and no row in it is ever
  updated or deleted.
- No controller contains a query or a rule; every refusal leaves the database
  as it was.
