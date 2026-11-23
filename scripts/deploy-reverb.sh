#!/usr/bin/env bash
#
# Deploy CHIPS with the Reverb websocket server (staff messaging live updates).
#
# Usage (run from the app directory on the server, as the deploy user):
#   scripts/deploy-reverb.sh             # normal deploy: pull, build, restart
#   scripts/deploy-reverb.sh --init      # first time only: also generate keys, install the cron job
#   scripts/deploy-reverb.sh --no-pull   # skip git pull (code already updated)
#
# Optional overrides:
#   APP_DIR=/var/www/chab-chips BRANCH=main scripts/deploy-reverb.sh
#
# Reverb is kept alive by the deploy user's crontab: it tries to start the server
# every minute, and flock makes that a no-op while it is already running. After a
# deploy, "reverb:restart" stops the old process and cron starts the new one within
# a minute. No sudo or Supervisor is needed.
#
set -euo pipefail

APP_DIR="${APP_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
BRANCH="${BRANCH:-main}"
LOCK_FILE="${LOCK_FILE:-/tmp/chips-reverb.lock}"
LOG_FILE="storage/logs/reverb.log"
CRON_MARKER="# chips-reverb"

INIT=false
PULL=true
for arg in "$@"; do
    case "$arg" in
        --init) INIT=true ;;
        --no-pull) PULL=false ;;
        *) echo "Unknown option: $arg" >&2; exit 1 ;;
    esac
done

step() { printf '\n==> %s\n' "$1"; }
fail() { printf '\nERROR: %s\n' "$1" >&2; exit 1; }
env_value() { grep -E "^$1=" .env | tail -n1 | cut -d= -f2- | tr -d '"' || true; }

# Running as root leaves root-owned files in storage/, vendor/ and bootstrap/cache
# that the web user then cannot write. Set ALLOW_ROOT=1 only if you really mean it.
if [ "$(id -u)" -eq 0 ] && [ "${ALLOW_ROOT:-0}" != "1" ]; then
    fail "Do not run this as root. Run it as the user that owns the app (e.g. sudo -u www-data $0)."
fi

cd "$APP_DIR"
[ -f artisan ] || fail "artisan not found in $APP_DIR. Set APP_DIR to the app folder."
[ -f .env ] || fail ".env not found in $APP_DIR."

# ---------------------------------------------------------------------------
# --init: add the Reverb variables to .env with fresh keys (only if missing)
# ---------------------------------------------------------------------------
if $INIT; then
    step "Adding Reverb variables to .env (skipped if already present)"
    if grep -q '^REVERB_APP_KEY=' .env; then
        echo "REVERB_APP_KEY already set, leaving the Reverb block as is."
    else
        read -r -p "Public domain for the site (e.g. chips.example.com): " DOMAIN
        [ -n "$DOMAIN" ] || fail "A domain is required."
        APP_ID=$(php -r 'echo random_int(100000, 999999);')
        APP_KEY=$(php -r 'echo bin2hex(random_bytes(10));')
        APP_SECRET=$(php -r 'echo bin2hex(random_bytes(10));')
        cp .env ".env.bak.$(date +%Y%m%d%H%M%S)"
        # Switch the broadcaster, replacing an existing line or adding one.
        if grep -q '^BROADCAST_CONNECTION=' .env; then
            sed -i.tmp 's/^BROADCAST_CONNECTION=.*/BROADCAST_CONNECTION=reverb/' .env && rm -f .env.tmp
        else
            echo 'BROADCAST_CONNECTION=reverb' >> .env
        fi
        cat >> .env <<EOF

REVERB_APP_ID=$APP_ID
REVERB_APP_KEY=$APP_KEY
REVERB_APP_SECRET=$APP_SECRET
REVERB_HOST=$DOMAIN
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080

VITE_REVERB_APP_KEY="\${REVERB_APP_KEY}"
VITE_REVERB_HOST="\${REVERB_HOST}"
VITE_REVERB_PORT="\${REVERB_PORT}"
VITE_REVERB_SCHEME="\${REVERB_SCHEME}"
EOF
        echo "Keys written to .env (a backup was saved as .env.bak.<timestamp>)."
    fi
fi

# ---------------------------------------------------------------------------
# Check the .env is ready before spending time on a build
# ---------------------------------------------------------------------------
step "Checking .env"
for var in REVERB_APP_ID REVERB_APP_KEY REVERB_APP_SECRET REVERB_HOST; do
    [ -n "$(env_value "$var")" ] || fail "$var is missing in .env. Run with --init or add it by hand."
done
[ "$(env_value BROADCAST_CONNECTION)" = "reverb" ] || fail "BROADCAST_CONNECTION must be reverb in .env."
[ "$(env_value REVERB_SCHEME)" = "https" ] || echo "WARNING: REVERB_SCHEME is not https. Browsers on an HTTPS site will block ws://."
echo ".env looks good."

# ---------------------------------------------------------------------------
# Code and dependencies
# ---------------------------------------------------------------------------
if $PULL; then
    step "Pulling $BRANCH"
    git fetch origin "$BRANCH"
    git checkout "$BRANCH"
    git pull --ff-only origin "$BRANCH"
fi

step "Checking tools"
command -v node >/dev/null && command -v npm >/dev/null || fail "Node and npm are not installed (Node 20.19+ or 22 is needed)."
NODE_MAJOR=$(node -v | sed -E 's/^v([0-9]+).*/\1/')
NODE_MINOR=$(node -v | sed -E 's/^v[0-9]+\.([0-9]+).*/\1/')
if [ "$NODE_MAJOR" -lt 20 ] || { [ "$NODE_MAJOR" -eq 20 ] && [ "$NODE_MINOR" -lt 19 ]; }; then
    fail "Node $(node -v) is too old for Vite 7. Install Node 22 (see NodeSource) and run again."
fi
PHP_BIN=$(command -v php) || fail "php not found."
FLOCK_BIN=$(command -v flock) || fail "flock not found (package util-linux)."

step "Installing PHP dependencies"
# Drop stale package caches first (e.g. dev-only Debugbar left from an earlier install);
# composer's own post-install artisan calls would fail on them.
rm -f bootstrap/cache/packages.php bootstrap/cache/services.php
composer install --no-dev --optimize-autoloader --no-interaction
php artisan package:discover --ansi

# VITE_REVERB_* values are baked in at build time, so build after .env is final.
step "Building front-end assets"
npm ci
npm run build

# ---------------------------------------------------------------------------
# Laravel
# ---------------------------------------------------------------------------
step "Refreshing Laravel caches"
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# ---------------------------------------------------------------------------
# Crontab entry that keeps Reverb running (added once, for the current user)
# ---------------------------------------------------------------------------
step "Checking the Reverb cron entry"
CRON_LINE="* * * * * cd $APP_DIR && $FLOCK_BIN -n $LOCK_FILE bash -c 'ulimit -n 10000 2>/dev/null; exec $PHP_BIN artisan reverb:start' >> $APP_DIR/$LOG_FILE 2>&1 $CRON_MARKER"
if crontab -l 2>/dev/null | grep -F "$CRON_MARKER" | grep -qF "cd $APP_DIR "; then
    echo "Cron entry already present."
else
    # Remove any old chips-reverb line (e.g. one pointing at a wrong folder) before adding the right one.
    ( crontab -l 2>/dev/null | grep -vF "$CRON_MARKER" || true ) | crontab - 2>/dev/null || true
    ( crontab -l 2>/dev/null; echo "$CRON_LINE" ) | crontab -
    echo "Cron entry added to the crontab of $(whoami)."
fi

# ---------------------------------------------------------------------------
# Restart long-running processes so they load the new code and config
# ---------------------------------------------------------------------------
step "Restarting Reverb and the queue workers"
# Signals the running Reverb to stop; the cron entry starts a fresh one within a minute.
php artisan reverb:restart
php artisan queue:restart

step "Status"
echo "Waiting up to 70 seconds for cron to (re)start Reverb..."
for _ in $(seq 1 14); do
    if ss -ltn 2>/dev/null | grep -q '127.0.0.1:8080'; then
        echo "Reverb is listening on 127.0.0.1:8080."
        UP=true
        break
    fi
    sleep 5
done
[ "${UP:-false}" = true ] || echo "WARNING: nothing is listening on 127.0.0.1:8080 yet. Check: tail $APP_DIR/$LOG_FILE and crontab -l"

cat <<'EOF'

Deploy finished. Still needed once (not done by this script):
  - nginx: add the /app and /apps proxy blocks to the HTTPS server, then: sudo nginx -t && sudo systemctl reload nginx
  - Check: open Messages, the pill should say "Live updates connected".
EOF
