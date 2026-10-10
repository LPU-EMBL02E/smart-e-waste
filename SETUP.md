# Setup

How to turn this repository into a running system. Two parts: the Laravel app in
`web/`, and the three firmware programs in `firmware/`.

Section references are to the files in `docs/`.

---

## 1. Install the web app

Needs PHP 8.4 and Composer on the machine doing the install. On the Pi:

```bash
sudo apt install php8.4-cli php8.4-fpm php8.4-mysql php8.4-mbstring \
                 php8.4-xml php8.4-curl php8.4-bcmath php8.4-zip unzip
```

From the repository root:

```bash
cd web
composer install
```

`composer.lock` pins Laravel 13 and every package to an exact version
(`docs/System_Plan.md` §3), so every machine and the Pi run the same code.

---

## 2. Configure and migrate

```bash
cd web
cp .env.example .env
php artisan key:generate        # NEW INSTALLS ONLY — see the warning below
```

> **Never run `key:generate` on a host that has existing data.** Device secrets
> are encrypted with `APP_KEY`. A new key makes every bin's secret unreadable
> and each one has to be issued a new secret. At handover, carry the existing
> `APP_KEY` across (`System_Plan.md` §8).

Create the database with an explicit character set and a dedicated user — not
`root`:

```sql
CREATE DATABASE ewaste CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'ewaste'@'localhost' IDENTIFIED BY '<password>';
GRANT ALL PRIVILEGES ON ewaste.* TO 'ewaste'@'localhost';
FLUSH PRIVILEGES;
```

The collation is set explicitly because MariaDB 11.8's own default does not
import into MySQL or older MariaDB, which would break the handover dump.

```bash
php artisan migrate
php artisan db:seed --class=FirstAdminSeeder
```

### Mail

Leave `MAIL_MAILER=log` until real mail is needed; it writes mail to the log
instead of sending it. To send through Resend, install its package:

```bash
composer require resend/resend-php
```

Application code only ever uses Laravel's `Mail` and `Notification` classes —
the Resend SDK is never called directly, which is what makes the switch to the
university's SMTP server an `.env` change at handover (`System_Plan.md` §7.2).

### Before the bin works at all

The seeded admin must do two things, in this order:

1. **Register the bin** at `/admin/bins`, enter its `empty_distance_mm` and its
   load cell calibration, and copy the secret — it is shown **once**.
2. **Create the first reward rule** at `/admin/points-rate`.

Until both exist every deposit is refused with `NO_REWARD_RULE`. The four rule
values need instructor approval first (`System_Plan.md` §5.7; guidelines,
Step 9).

---

## 3. Front end

Tailwind is compiled with Vite on a **dev machine**, never on the Pi
(`System_Plan.md` §3). Build there and deploy `public/build`:

```bash
npm ci
npm run build
```

---

## 4. Serve it

```bash
# development
php artisan serve

# production: Nginx + PHP 8.4-FPM, document root web/public
```

One cron entry covers all scheduled work (`routes/console.php`):

```cron
* * * * * php /path/to/web/artisan schedule:run >> /dev/null 2>&1
```

Then `cloudflared` as a systemd service on the project domain, and
`mariadb-dump` on a schedule (`System_Plan.md` §7, steps 8–9).

---

## 5. Verify before trusting any of it

```bash
# The signing contract — no server needed.
python3 tools/simulator/test_vectors.py

# The two portability rules from System_Plan.md §8.1.
./scripts/check-swappability.sh

# Every route, with its module's middleware applied.
cd web && php artisan route:list
```

`route:list` should show the seven device routes under `api/v1/device` with
`device.signed` on all but `time`, and every `/admin` route with both `auth`
and `admin`.

Then, against a running server with a registered bin:

```bash
cd tools/simulator
python3 simulate.py --url http://127.0.0.1:8000 \
    --device BIN-01 --secret <64-hex> \
    deposit --qr <student-token> --weight 184.6
```

Work through the scenarios in `tools/simulator/README.md` before relying on real
hardware. They cover the cases from `API_Design.md` §8 that are awkward to
produce with a bin on the bench — a repeated `complete`, an expired session, a
bad signature, clock skew.

---

## 6. Firmware

See `firmware/README.md`. In short:

```bash
cd firmware/controller
pio run -e controller -t upload
pio device monitor
```

No Wi-Fi credentials, server URL, device code or secret are compiled in. They
are entered once through the setup portal and kept in the NodeMCU's
non-volatile storage, which is what lets a bin be re-pointed at another server
without reflashing.

---

## 7. What is not built yet

`web/` is boilerplate: the schema, the models, the routes, the error contract
and the module wiring are real, and the controllers, services and listeners are
documented skeletons whose bodies raise `LogicException`. Nothing in the
request path is implemented yet, and the firmware modules are headers with
stub bodies.

`README.md` §"State of the repository" lists what is real, what is a skeleton,
and the decisions still open from `docs`.
