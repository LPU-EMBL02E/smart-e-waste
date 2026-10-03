#!/usr/bin/env bash
#
# The two swappability checks from System_Plan.md §8.1, which §10 lists as the
# first item in the build order.
#
#   1. No vendor SDK is imported anywhere in web/app/.
#   2. env() is called only inside web/config/.
#
# Why these two and not a longer list: together they are what keeps the app
# portable at handover. If application code imports a vendor SDK, that service
# can no longer be swapped in .env; if env() is read outside config/, the value
# is invisible to `php artisan config:cache` and the setting silently stops
# being a setting.
#
#   ./scripts/check-swappability.sh
#
# Exits 0 when both pass, 1 otherwise. Suitable for CI or a pre-commit hook.

set -uo pipefail

cd "$(dirname "$0")/.." || exit 1

APP_DIR="web/app"
CONFIG_DIR="web/config"
failures=0

if [[ ! -d "$APP_DIR" ]]; then
    echo "skip: $APP_DIR does not exist yet (run the composer step in SETUP.md first)"
    exit 0
fi

# --------------------------------------------------------------------------
# 1. No vendor SDK imports in app/
# --------------------------------------------------------------------------
# Nothing from Resend, Cloudflare or a cloud provider may be imported in
# application code. Mail goes through Laravel's Mail and Notification classes,
# and the delivery service is an .env setting.

echo "1. vendor SDK imports in $APP_DIR/"

VENDOR_PATTERN='^\s*use\s+(Resend|Cloudflare|Aws|Google\\Cloud|Azure|Twilio|Stripe|Pusher)\\'

if matches=$(grep -rInE "$VENDOR_PATTERN" "$APP_DIR" 2>/dev/null); then
    echo "   FAIL — application code must not import a vendor SDK:"
    echo "$matches" | sed 's/^/     /'
    echo "     Use Laravel's own interfaces (Mail, Notification, Storage, Log)"
    echo "     and name the service in .env instead (System_Plan.md §8.1)."
    failures=$((failures + 1))
else
    echo "   ok"
fi

# --------------------------------------------------------------------------
# 2. env() only inside config/
# --------------------------------------------------------------------------
# Everything else reads config(). An env() call outside config/ returns null
# once the configuration is cached, which is how a setting quietly stops
# working in production and nowhere else.

echo "2. env() calls outside $CONFIG_DIR/"

if matches=$(grep -rIn --include='*.php' -E '(^|[^a-zA-Z_>$])env\s*\(' "$APP_DIR" 2>/dev/null); then
    echo "   FAIL — env() must not be called outside config/:"
    echo "$matches" | sed 's/^/     /'
    echo "     Add the value to config/ewaste.php (or another config file) and"
    echo "     read it with config() (System_Plan.md §8.1)."
    failures=$((failures + 1))
else
    echo "   ok"
fi

echo
if (( failures == 0 )); then
    echo "Both checks passed."
    exit 0
fi

echo "$failures check(s) failed."
exit 1
