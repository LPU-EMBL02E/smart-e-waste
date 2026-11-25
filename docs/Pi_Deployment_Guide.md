# Deploying to the Raspberry Pi 4

A step-by-step guide to self-hosting the web app on the project's Raspberry Pi 4.
It follows the plan in `System_Plan.md` §7 and adds the commands. Where this
guide and `System_Plan.md` disagree, `System_Plan.md` wins and this guide is
corrected.

`SETUP.md` covers installing and running the app on any machine. This guide is
only about the Pi: the operating system, Nginx, the tunnel, the scheduler,
backups and updates.

**What this guide sets up:** the Laravel app in `web/`, served by Nginx and
PHP-FPM, with MariaDB, reached by browsers through a Cloudflare Tunnel and by
the bin over the local network. The bin station (`firmware/station/`) also runs
on this Pi but is not built yet; see step 13.

---

## Contents

1. [What runs where](#1-what-runs-where)
2. [What you need](#2-what-you-need)
3. [Install the operating system](#3-install-the-operating-system)
4. [First boot](#4-first-boot)
5. [Install the server packages](#5-install-the-server-packages)
6. [Create the database](#6-create-the-database)
7. [Put the app on the Pi](#7-put-the-app-on-the-pi)
8. [Configure the app](#8-configure-the-app)
9. [Build and copy the front-end assets](#9-build-and-copy-the-front-end-assets)
10. [Migrate, seed and cache](#10-migrate-seed-and-cache)
11. [Serve it with Nginx and PHP-FPM](#11-serve-it-with-nginx-and-php-fpm)
12. [Make it reachable: Cloudflare Tunnel](#12-make-it-reachable-cloudflare-tunnel)
13. [The bin station](#13-the-bin-station)
14. [The scheduler](#14-the-scheduler)
15. [Before the bin works](#15-before-the-bin-works)
16. [Check that it works](#16-check-that-it-works)
17. [Backups](#17-backups)
18. [Updating to a new version](#18-updating-to-a-new-version)
19. [The LAN demo fallback](#19-the-lan-demo-fallback)
20. [Troubleshooting](#20-troubleshooting)

---

## 1. What runs where

```
 Browsers (students, admins)                  The bin
          │ HTTPS                                │
          ▼                                      │ plain HTTP + HMAC signature,
   Cloudflare Tunnel ── cloudflared on the Pi    │ over the bin's own router
          │ http://localhost:80                  │
          ▼                                      ▼
 ┌──────────────────────── Raspberry Pi 4 ────────────────────────┐
 │  Nginx :80  ──►  PHP 8.4-FPM  ──►  Laravel app (web/)           │
 │                                      │                          │
 │                                      ▼                          │
 │                               MariaDB 11.8 (localhost only)     │
 │  cron ──► php artisan schedule:run, every minute                │
 │  Bin station (firmware/station/, not built yet)                 │
 └─────────────────────────────────────────────────────────────────┘
```

- **Browsers use the tunnel hostname only**, over HTTPS. That is the single
  canonical URL, which keeps secure session cookies on (`System_Plan.md` §7).
- **The bin uses the Pi's local address** over plain HTTP. Every device request
  is signed, so deposits never depend on the internet.
- **Assets are built on a dev machine, never on the Pi** (`System_Plan.md` §3).
  The Pi has no Node.js.

---

## 2. What you need

**Hardware** (`System_Plan.md` §2)

- Raspberry Pi 4 Model B, 4 GB, with the official 5 V 3 A power supply.
- Two high-endurance microSD cards (32–64 GB, A1 or A2 rated): one the Pi
  boots from, and a spare for the disk image (step 17). Use cards rated for
  continuous recording; ordinary cards wear out quickly under a database
  (`System_Plan.md` §2).
- A heatsink and fan on the Pi. It sits in a closed bin and also runs the
  e-waste model.
- The bin's dedicated router, with an internet uplink for the tunnel and email.

**Accounts**

- The project's GitHub repository, and a way for the Pi to read it (step 7).
- A domain on a Cloudflare account, registered under a transferable account,
  not a personal one (`System_Plan.md` §8).
- The team Gmail account with 2-Step Verification and an app password
  (`System_Plan.md` §7.2, `SETUP.md` §2 "Mail").

**On your dev machine:** Git, Node.js and npm (to build the assets), and
Raspberry Pi Imager.

---

## 3. Install the operating system

1. In Raspberry Pi Imager, choose **Raspberry Pi 4**, then **Raspberry Pi OS
   Lite (64-bit)** (Trixie, Debian 13), and the microSD card as the storage.
2. Open the OS customisation settings and set:
   - **Hostname**, for example `ewaste`. The Pi is then also reachable as
     `ewaste.local` on the bin's network.
   - **A username and a strong password.**
   - **Wi-Fi:** the bin's router, or plug the Pi into it with a cable.
   - **Locale:** time zone `Asia/Manila` is fine. The app stores every time in
     UTC regardless: its database connection is pinned to `+00:00`.
   - **Services:** enable SSH, with public-key authentication only, and paste
     your public key.
3. Write the image, put the card in the Pi's microSD slot, and power it on.

**Always shut the Pi down before unplugging it:** `sudo poweroff`, then wait for
the green light to stop flashing. Cutting power while MariaDB is writing is the
most common way an SD card gets corrupted (`System_Plan.md` §2).

**Give the Pi a fixed address.** In the router's settings, reserve an IP address
for the Pi (a DHCP reservation), for example `192.168.50.10`. The bin is
configured with this address, so it must not change. This guide uses
`192.168.50.10` as the example; use your own.

---

## 4. First boot

From your dev machine:

```bash
ssh <username>@ewaste.local
```

Bring the system up to date and turn on automatic security updates:

```bash
sudo apt update && sudo apt full-upgrade -y
sudo apt install -y unattended-upgrades
sudo dpkg-reconfigure -plow unattended-upgrades   # answer Yes
sudo reboot
```

**Optional firewall.** Only SSH and HTTP need to be reachable, and only from the
bin's network. MariaDB already listens on `localhost` only.

```bash
sudo apt install -y ufw
sudo ufw allow from 192.168.50.0/24 to any port 22 proto tcp
sudo ufw allow from 192.168.50.0/24 to any port 80 proto tcp
sudo ufw enable
```

Change `192.168.50.0/24` to your router's network. The tunnel needs no open
port: `cloudflared` connects outwards. When the bin station is built, open its
port to the bin's network too.

---

## 5. Install the server packages

Everything comes from Raspberry Pi OS's own repositories, so the Pi stays
maintainable with plain `apt upgrade` (`System_Plan.md` §3, version policy):

```bash
sudo apt install -y nginx mariadb-server \
    php8.4-fpm php8.4-cli php8.4-mysql php8.4-mbstring php8.4-xml \
    php8.4-curl php8.4-bcmath php8.4-zip \
    composer git unzip
```

Check the versions:

```bash
php -v          # PHP 8.4
mariadb --version   # 11.8
nginx -v        # 1.26
composer --version
```

No Docker, Redis, queue worker or Node.js is needed.

---

## 6. Create the database

```bash
sudo mariadb
```

```sql
CREATE DATABASE ewaste CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'ewaste'@'localhost' IDENTIFIED BY '<a long random password>';
GRANT ALL PRIVILEGES ON ewaste.* TO 'ewaste'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

- **Use a dedicated user, never `root`** (`System_Plan.md` §7, step 3).
- **Set the collation explicitly.** MariaDB 11.8's own default doesn't import
  into MySQL or older MariaDB, which would break the handover dump
  (`SETUP.md` §2).

You can generate a password with `openssl rand -base64 24`.

---

## 7. Put the app on the Pi

The app lives in `/var/www/smart-e-waste`. Your user owns the files; the web
server's group, `www-data`, can read them and write only where Laravel needs to.

**Let the Pi read the repository.** If it is private, give the Pi a read-only
deploy key:

```bash
ssh-keygen -t ed25519 -C "ewaste-pi deploy key" -f ~/.ssh/id_ed25519 -N ""
cat ~/.ssh/id_ed25519.pub
```

Add that public key in GitHub under the repository's **Settings → Deploy keys**,
with **Allow write access** left off.

**Clone and install:**

```bash
sudo mkdir -p /var/www/smart-e-waste
sudo chown "$USER":www-data /var/www/smart-e-waste
git clone git@github.com:LPU-EMBL02E/smart-e-waste.git /var/www/smart-e-waste

cd /var/www/smart-e-waste/web
composer install --no-dev --optimize-autoloader
```

`composer.lock` pins every package, so the Pi runs exactly the versions that were
tested.

**Let the web server write to `storage` and `bootstrap/cache` only:**

```bash
sudo chgrp -R www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod g+s {} +
```

> **Run every `php artisan` command on the Pi as `www-data`**, as in
> `sudo -u www-data php artisan …`, except `key:generate` in step 8. Files
> Laravel creates, such as the day's log file or the caches, then belong to the
> web server. Running artisan as your own user creates files the web server
> can't write, and pages start failing.

---

## 8. Configure the app

```bash
cd /var/www/smart-e-waste/web
cp .env.example .env
sudo chgrp www-data .env
chmod 640 .env
```

**Only on a new install with no data**, generate the app key. This one command
runs as your own user, because it writes to `.env`, which `www-data` can only
read:

```bash
php artisan key:generate
```

> **Never run `key:generate` on a Pi that has existing data.** Device secrets are
> encrypted with `APP_KEY`. A new key makes every bin's secret unreadable, and
> each bin has to be issued a new one. Keep a copy of `.env` somewhere safe: it
> is the only copy of the key (`System_Plan.md` §7, step 9 and §8).

Edit `.env` (`nano .env`) and set at least these. `.env.example` explains every
setting.

| Setting | Value |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false`. Never `true` on the Pi: error pages would show secrets |
| `APP_URL` | The tunnel hostname, for example `https://ewaste.example.edu.ph` |
| `DB_PASSWORD` | The password from step 6 |
| `SESSION_SECURE_COOKIE` | `true` |
| `QUEUE_CONNECTION` | `sync` (already the default) |
| `MAIL_MAILER` | `smtp` |
| `MAIL_USERNAME`, `MAIL_FROM_ADDRESS` | The team Gmail address |
| `MAIL_PASSWORD` | The Gmail app password |
| `LOG_LEVEL` | `warning` |
| `EWASTE_FIRST_ADMIN_EMAIL` | A real address you can read; it gets the first password link |

Leave the other `EWASTE_*` settings at their defaults unless the team has decided
otherwise (`System_Plan.md` §6.3). `EWASTE_HARDWARE_MAX_WEIGHT_G` stays empty
until it is measured (`API_Design.md` P37).

**`.env` holds secrets.** Never commit it, copy it into the repository or paste
it into a chat. AGENTS.md §2, rule 10.

---

## 9. Build and copy the front-end assets

Tailwind is compiled with Vite on your dev machine (`System_Plan.md` §3, §7
step 6). `web/public/build` is not in Git, so it is copied to the Pi.

**On your dev machine**, at the same commit the Pi has:

```bash
cd smart-e-waste
git fetch && git checkout <the same commit or branch as the Pi>
cd web
npm ci
npm run build
rsync -a --delete public/build/ <username>@ewaste.local:/var/www/smart-e-waste/web/public/build/
```

The built files have hashed names listed in `public/build/manifest.json`. Assets
from a different commit than the PHP code can leave pages unstyled, so build and
copy them on every update (step 18).

---

## 10. Migrate, seed and cache

On the Pi:

```bash
cd /var/www/smart-e-waste/web
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan db:seed --class=FirstAdminSeeder --force
sudo -u www-data php artisan optimize
```

- **`--force`** is needed because `APP_ENV` is `production`.
- **`FirstAdminSeeder`** creates one admin, with the email in
  `EWASTE_FIRST_ADMIN_EMAIL` and no password yet. Step 15 sets the password.
- **`optimize`** caches the configuration, routes, events and views, which saves
  work on every request on the Pi.

> **After any change to `.env`, run `sudo -u www-data php artisan optimize`
> again.** The configuration is cached, so `.env` changes are ignored until it is
> rebuilt.

---

## 11. Serve it with Nginx and PHP-FPM

**PHP settings.** A CSV import accepts files up to 2 MB
(`ImportUsersRequest`). Give uploads some headroom:

```bash
sudo tee /etc/php/8.4/fpm/conf.d/99-ewaste.ini > /dev/null <<'EOF'
; web/ on the Pi. The CSV import allows 2 MB files.
upload_max_filesize = 4M
post_max_size = 8M
expose_php = Off
EOF
sudo systemctl restart php8.4-fpm
```

OPcache is installed and on by default with `php8.4-fpm`.

**The Nginx site.** One site serves both the tunnel and the bin. This is the
configuration from Laravel's deployment documentation, with the paths for this
project:

```bash
sudo tee /etc/nginx/sites-available/ewaste > /dev/null <<'EOF'
# The EcoCircuit web app (docs/Pi_Deployment_Guide.md §11).
# Browsers arrive through cloudflared on localhost; the bin arrives over the LAN.
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name _;
    root /var/www/smart-e-waste/web/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;
    charset utf-8;

    # The CSV import allows 2 MB files; leave room for the rest of the form.
    client_max_body_size 4m;

    # Built assets have hashed names, so browsers can keep them for a year.
    location /build/ {
        try_files $uri =404;
        expires 1y;
        access_log off;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF
sudo ln -s /etc/nginx/sites-available/ewaste /etc/nginx/sites-enabled/ewaste
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```

**The real client IP.** The app trusts only the local hop (`127.0.0.1`) and
reads the standard `X-Forwarded-For` and `X-Forwarded-Proto` headers
(`bootstrap/app.php`, `System_Plan.md` §7.1). `cloudflared` connects from
`localhost` and sends those headers, so sign-in rate limits see each visitor's
own address, and Laravel knows the page was reached over HTTPS. Requests from
the bin come straight from the LAN and are not trusted as a proxy. Nothing in
the Nginx file needs to change for this.

**Busy pages?** Debian's PHP-FPM starts with `pm.max_children = 5` in
`/etc/php/8.4/fpm/pool.d/www.conf`. If pages queue when many students use the
site at once, raise it to 8–10 and restart `php8.4-fpm`. Each worker uses about
40–60 MB, and the e-waste model needs room as well (`System_Plan.md` §8.3).

---

## 12. Make it reachable: Cloudflare Tunnel

The tunnel lets browsers reach the Pi from anywhere, over HTTPS, with no port
open on the router (`System_Plan.md` §7, step 8). Laravel never calls Cloudflare;
the tunnel can be replaced later without changing the app (§7.1).

1. In the Cloudflare dashboard, open **Zero Trust → Networks → Tunnels** and
   create a tunnel of type **Cloudflared**, named for example `ewaste-pi`.
2. Choose **Debian** and **arm64**. Cloudflare shows the commands to install
   `cloudflared` and to run it as a service with your tunnel's token. Run them on
   the Pi. The service command looks like
   `sudo cloudflared service install <token>`.
3. Add a **public hostname**: the subdomain and domain you chose, with the
   service set to **HTTP** and the URL `localhost:80`.
4. Set `APP_URL` in `.env` to `https://` plus that hostname, then run
   `sudo -u www-data php artisan optimize`.

Check it:

```bash
systemctl status cloudflared   # active (running)
```

Then open `https://<your hostname>/login` in a browser.

> **`cloudflared` is the one package from outside Raspberry Pi OS.** Debian
> doesn't ship it, so Cloudflare's own package repository is used. It is
> network infrastructure, not part of the app, and it updates with `apt upgrade`
> once installed.

---

## 13. The bin station

The bin station is the Python program on this Pi that runs the QR scanner, the
camera check with its e-waste model, and the touchscreen
(`System_Plan.md` §8.3). **It is not built yet**
(`firmware/docs/TRACK-E-BIN-STATION.md`). When it is, its setup is
`System_Plan.md` §7, step 10:

```bash
sudo apt install python3-picamera2 python3-numpy python3-serial python3-onnxruntime chromium cage
```

Copy the e-waste model file to the path in the station's settings file, run the
station as a systemd service, and start Chromium in kiosk mode under `cage`,
pointed at the station's screen page on `localhost`. Then enter the station's
address in the controller's setup portal. Training never runs on the Pi.

---

## 14. The scheduler

One cron entry runs all scheduled work (`routes/console.php`): expiring deposit
sessions every minute, and deleting accounts never confirmed every hour
(`API_Design.md` P54). Add it to the web server's crontab:

```bash
sudo crontab -u www-data -e
```

```cron
* * * * * cd /var/www/smart-e-waste/web && php artisan schedule:run >> /dev/null 2>&1
```

Check that Laravel sees the tasks:

```bash
cd /var/www/smart-e-waste/web
sudo -u www-data php artisan schedule:list
```

---

## 15. Before the bin works

**Set the first admin's password.** The seeded admin has no password until it is
set through a link (`SETUP.md` §2):

1. Open `https://<your hostname>/forgot-password` and enter the first admin's
   email.
2. Open the emailed link and choose a password. Password links last 7 days
   (`API_Design.md` P51).

If email isn't working yet, see `SETUP.md` §2 "First admin's password" for
getting the link from the log instead.

**Then, signed in as the admin, in this order** (`SETUP.md` §2):

1. **Register the bin** at `/admin/bins`. Enter its `empty_distance_mm` and its
   load cell calibration, and copy the secret: it is shown **once**.
2. **Create the first reward rule** at `/admin/points-rate`. Its four values need
   the instructor's approval first (`System_Plan.md` §5.7).

Until both exist, every deposit is refused with `NO_REWARD_RULE`.

**Point the bin at the Pi.** In the controller's setup portal, enter the bin's
Wi-Fi, the API base URL `http://192.168.50.10` (scheme, host and optional port,
`API_Design.md` P1), the device code and the secret. Use the reserved IP address
rather than `ewaste.local`: it doesn't depend on name lookup working on the
ESP32.

---

## 16. Check that it works

```bash
cd /var/www/smart-e-waste/web
sudo -u www-data php artisan about        # Environment: production, Debug Mode: OFF, caches on
sudo -u www-data php artisan route:list   # see SETUP.md §5 for what to look for
curl -s http://192.168.50.10/api/v1/device/time   # JSON with the server time
```

- **In a browser:** the tunnel URL loads, you can sign in, and a registration
  sends its confirmation email.
- **The device API:** run the simulator from your dev machine against the Pi's
  LAN address, with the bin's device code and secret (`SETUP.md` §5,
  `tools/simulator/README.md`).
- **The scheduler:** `schedule:list` shows each task and when it runs next.
  After a few minutes, nothing new about the scheduler appears in
  `storage/logs/`.

---

## 17. Backups

`System_Plan.md` §7, step 9 asks for three things.

**1. A nightly database dump.** As root, which signs in to MariaDB through the
system account, with no password stored anywhere:

```bash
sudo mkdir -p /var/backups/ewaste
sudo chmod 700 /var/backups/ewaste
sudo crontab -e
```

```cron
# Nightly dump of the ewaste database at 02:30, kept for 14 days.
30 2 * * * mariadb-dump --single-transaction --default-character-set=utf8mb4 ewaste | gzip > /var/backups/ewaste/ewaste-$(date +\%F).sql.gz && find /var/backups/ewaste -name '*.sql.gz' -mtime +14 -delete
```

- **Copy the dumps off the Pi** regularly, for example with `rsync` to another
  computer. A backup on the same card doesn't survive the card failing.
- **Restore** into an empty `ewaste` database:
  `gunzip -c ewaste-2026-11-22.sql.gz | sudo mariadb ewaste`.
- **A handover dump** needs one change: delete the first line if it starts with
  `/*M!999999`. MySQL and older MariaDB clients can't read it
  (`System_Plan.md` §8):
  `gunzip -c ewaste-2026-11-22.sql.gz | sed '1{/^\/\*M!999999/d}' > handover.sql`.

**2. A copy of `.env`**, kept somewhere safe off the Pi. Without its `APP_KEY`,
the device secrets in a dump can't be read.

**3. A full disk image** of the configured Pi. Shut the Pi down, put its card
in a card reader on another Linux computer, find its device name with `lsblk`,
and copy it:

```bash
sudo dd if=/dev/sdX of=ewaste-pi.img bs=4M status=progress
```

Write that image to the spare card with Raspberry Pi Imager (**Use custom**) and
keep the spare with the Pi. If the card fails, swap in the spare and restore the
latest dump. Make a new image after each update (step 18).

---

## 18. Updating to a new version

On the Pi:

```bash
cd /var/www/smart-e-waste
git pull --ff-only
cd web
composer install --no-dev --optimize-autoloader
```

On your dev machine, build and copy the assets for the same commit (step 9).

Back on the Pi:

```bash
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan optimize
sudo systemctl reload php8.4-fpm
```

- **Update when no one is depositing.** Migrations take seconds.
- **Don't use `php artisan down` while the bin is in use.** In maintenance mode
  the device API answers a plain 503 instead of its error shape
  (`API_Design.md` §3.5). The bin then treats the server as unreachable and
  refuses deposits, which is safe but confusing at the bin.
- **Never run `key:generate` on an update.**
- **If something goes wrong,** `git checkout <the previous commit>`, run the
  same commands, and restore last night's dump if a migration changed data.

---

## 19. The LAN demo fallback

Browsers reach the site only through the tunnel. If the campus internet or
Cloudflare is down, the bin keeps working but the dashboard is unreachable
(`System_Plan.md` §7). Keep a second settings file ready:

```bash
cd /var/www/smart-e-waste/web
cp -p .env .env.lan
```

In `.env.lan`, set:

```dotenv
APP_URL=http://192.168.50.10
SESSION_SECURE_COOKIE=false
```

`cp -p` keeps the file readable only by you and the web server.

**To switch to it:**
`cp -p .env .env.tunnel && cp -p .env.lan .env && sudo -u www-data php artisan optimize`.
**To switch back:**
`cp -p .env.tunnel .env && sudo -u www-data php artisan optimize`.

Browsers on the bin's network then use `http://192.168.50.10`. No email goes out
while the internet is down. Rehearse the switch once before the final
demonstration.

---

## 20. Troubleshooting

| What you see | Likely cause | What to do |
|---|---|---|
| **502 Bad Gateway** | PHP-FPM isn't running | `sudo systemctl status php8.4-fpm`, then `sudo systemctl restart php8.4-fpm` |
| **500 error, blank page** | Permissions on `storage` or `bootstrap/cache`, often after running artisan as your own user | Repeat the permission commands in step 7, and read the newest file in `storage/logs/` |
| **419 Page Expired** on every form | Secure cookies over plain HTTP: `SESSION_SECURE_COOKIE=true` but the browser used the LAN address | Use the tunnel URL, or switch to the LAN fallback (step 19) |
| **Links and redirects use `http://`** behind the tunnel | `APP_URL` is wrong, or the configuration cache is old | Fix `APP_URL`, then `sudo -u www-data php artisan optimize` |
| **A `.env` change has no effect** | The configuration is cached | `sudo -u www-data php artisan optimize` |
| **Pages have no styling** | `public/build` is missing or from a different commit | Build and copy the assets again (step 9) |
| **413 Request Entity Too Large** on CSV import | Nginx's upload limit | Check `client_max_body_size` in step 11 |
| **Emails don't arrive** | Mail settings, or Gmail refused the login | Read `storage/logs/`. See `SETUP.md` §2 "Mail" and the sending limits in `System_Plan.md` §7.2 |
| **The tunnel URL doesn't load** | `cloudflared` stopped, or the public hostname points elsewhere | `systemctl status cloudflared`; check the hostname's service is `http://localhost:80` |
| **The bin can't reach the server** | Wrong API base URL, the Pi's address changed, or the firewall | `curl http://192.168.50.10/api/v1/device/time` from another computer on the bin's network; check the DHCP reservation and `sudo ufw status` |
| **Deposits refused with `NO_REWARD_RULE`** | No reward rule yet | Create one at `/admin/points-rate` (step 15) |
| **The Pi is slow or restarts** | Heat or power | `vcgencmd measure_temp` and `vcgencmd get_throttled` (`0x0` is healthy). Check the fan and use the official power supply |

---

**At handover**, the school gets this Pi already configured, together with the
items listed in `System_Plan.md` §8.
