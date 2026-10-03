# web/ — the Laravel application

**This directory is not a complete Laravel project.** It holds only this
project's own files. The framework skeleton — `artisan`, `composer.json`,
`bootstrap/`, `public/`, the stock `config/*.php` — comes from
`composer create-project`, and the reasoning is in
[../SETUP.md](../SETUP.md) §1.

Run the two commands in SETUP.md §2 before expecting anything here to run.

## Structure

A modular monolith: one Laravel app, one database, organized by domain.
`docs/System_Plan.md` §4.

| Module | Owns | Logic in |
|---|---|---|
| `Identity` | `organizations`, `users`, `user_qr_credentials` | Laravel's built-in auth |
| `Devices` | `devices`, `bin_telemetry` | `BinService`, the signature middleware |
| `Deposits` | `deposit_sessions`, `transactions` | `DepositService` |
| `Rewards` | `reward_rules`, `point_ledger`, `rewards`, `redemptions` | `PointsService`, `RedemptionService`, `RewardRuleService` |
| `Reporting` | nothing | read-only queries |
| `Notifications` | `notifications` | event listeners |

Each module holds `Models/`, `Http/Controllers/`, `Http/Requests/`, `Services/`,
`Events/`, `Support/` and `routes/`. `ModuleServiceProvider` loads them all.

## The three boundary rules

These are what make the module split mean something. Breaking one is a design
change, not a shortcut:

1. **Writes go through the owning module's service.** Deposits never inserts
   into `point_ledger`; it calls `PointsService` inside the same database
   transaction. `PointsService` is the only code that writes the ledger.
2. **Reads can cross modules.** Reporting and Eloquent relations query whatever
   they need.
3. **Modules announce, they don't call Notifications.** They dispatch
   `DepositCompleted`, `RedemptionFulfilled` and `BinFull`; listeners react.

## Two kinds of route

| Caller | Routes | Auth |
|---|---|---|
| The bin's NodeMCU | `/api/v1/device/*`, JSON, stateless | HMAC-SHA256 signature + timestamp |
| Student | Blade pages and form posts | Session cookie + CSRF |
| Admin | the same, under `/admin` | the same, with a role check on every route |

Only the device endpoints are a JSON API. `/admin/dashboard/summary` is the one
JSON web route, and it uses the normal session — not the device scheme.

```bash
php artisan route:list
```

## Portability rules this code follows

`docs/System_Plan.md` §8.1. Both are enforced by
`../scripts/check-swappability.sh`:

- **No vendor SDK is imported anywhere in `app/`.** Mail goes through Laravel's
  `Mail` and `Notification`; the delivery service is an `.env` setting.
- **`env()` is called only inside `config/`.** Everything else reads `config()`,
  or the value silently becomes null once the config is cached.

Also: Eloquent only, no MariaDB-specific SQL; files through Laravel's `Storage`;
every URL, proxy and cookie setting in `.env`.

## Conventions

- **Every timestamp is UTC.** Convert to `config('ewaste.display_timezone')`
  only when displaying.
- **No balance column exists.** A points balance is `SUM(points_delta)` over
  `point_ledger`, which is append-only. Do not add one.
- **Status columns are VARCHAR**, validated in the application. The allowed
  values live in the PHP enums under each module's `Support/`.
- **No delete routes.** Users, organizations, rewards and bins are switched off
  with their `status` / `is_active` column, because other tables reference them.
- **Plain Blade forms** with `@csrf`, and `@method('PUT')` where needed. The
  only JavaScript is the dashboard's polling script.

## Where the rules are written down

Each controller and service carries the rules it must follow in its docblock,
with the `docs/` section it comes from. When code and docblock disagree, `docs/`
wins — it is the source of truth.
