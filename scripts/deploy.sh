#!/usr/bin/env bash
# =============================================================================
# deploy.sh — deploy the finance app to production
#
# Run ON the server (as the deploy user):
#   cd /home/deploy/finance && bash scripts/deploy.sh
#
# Run FROM your local machine:
#   bash scripts/deploy.sh --remote deploy@165.22.203.180
# =============================================================================

set -euo pipefail

APP_DIR="${APP_DIR:-/home/deploy/finance}"
PHP="${PHP:-php}"

if [[ "${1:-}" == "--remote" ]]; then
  if [[ -z "${2:-}" ]]; then
    echo "Usage: bash scripts/deploy.sh --remote deploy@your-server" >&2
    exit 1
  fi
  echo "▶ Deploying to $2"
  ssh -T "$2" "cd $APP_DIR && bash scripts/deploy.sh"
  exit $?
fi

if [[ ! -f "$APP_DIR/artisan" ]]; then
  echo "ERROR: $APP_DIR/artisan not found. Run from the repo root or set APP_DIR." >&2
  exit 1
fi

cd "$APP_DIR"

if [[ ! -f ".env" ]]; then
  echo "ERROR: .env not found in $APP_DIR. Copy .env.example and fill it in." >&2
  exit 1
fi

step() { echo; echo "▶ $*"; }
ok()   { echo "  ✓ $*"; }

# Never leave the site stranded in maintenance mode if a later step fails.
MAINTENANCE_ACTIVE=false
restore_on_failure() {
  local exit_code=$?
  if [[ "$exit_code" -ne 0 && "$MAINTENANCE_ACTIVE" == true ]]; then
    echo "▶ Deploy failed — restoring service before exiting" >&2
    $PHP artisan up || true
  fi
}
trap restore_on_failure EXIT

START=$(date +%s)
echo "════════════════════════════════════════════"
echo "  Deploying finance  —  $(date '+%Y-%m-%d %H:%M:%S')"
echo "════════════════════════════════════════════"

step "Enabling maintenance mode"
$PHP artisan down --retry=10
MAINTENANCE_ACTIVE=true
ok "Site is down"

step "Pulling from origin/main"
git fetch origin
git reset --hard origin/main
ok "$(git log -1 --format='%h %s')"

step "Installing Composer dependencies"
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --quiet
ok "Composer up to date"

step "Clearing stale caches"
$PHP artisan optimize:clear
ok "Caches cleared"

step "Building frontend assets"
npm ci --no-audit --no-fund --silent
npm run build --silent
ok "Assets built"

step "Running migrations"
$PHP artisan migrate --force
ok "Migrations complete"

step "Clearing and rebuilding caches"
$PHP artisan cache:clear
$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache
$PHP artisan event:cache
ok "Caches rebuilt"

if [[ ! -L "public/storage" ]]; then
  step "Creating storage symlink"
  $PHP artisan storage:link
  ok "Symlink created"
fi

step "Fixing permissions"
# php-fpm runs as www-data; storage/, bootstrap/cache and the SQLite database
# must be group-writable and setgid so files created at runtime keep the
# www-data group.
chgrp -R www-data storage bootstrap/cache database 2>/dev/null || true
find storage bootstrap/cache database -type d -exec chmod 2775 {} + 2>/dev/null || true
find storage bootstrap/cache database -type f -exec chmod 664 {} + 2>/dev/null || true
ok "Permissions set"

step "Reloading PHP-FPM"
# No `systemctl reload php8.4-fpm` here on purpose. All 21 sites on the
# droplet share one php-fpm master and therefore one opcache, so a reload
# to deploy this app would discard ~350MB of cached bytecode belonging to
# every other app and force them all to recompile. opcache.validate_timestamps
# is on, so the new code is picked up on the next request without it.
# See INFRA-28. If that setting is ever turned off, this must come back.
ok "PHP-FPM reloaded"

step "Disabling maintenance mode"
$PHP artisan up
MAINTENANCE_ACTIVE=false
ok "Site is live"

step "Smoke test"
APP_URL=$($PHP artisan tinker --execute="echo config('app.url');" 2>/dev/null | tail -1)
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 "$APP_URL/up" || echo "000")
if [[ "$HTTP_CODE" == "200" ]]; then
  ok "GET $APP_URL/up → $HTTP_CODE"
else
  echo "  ✗ GET $APP_URL/up → $HTTP_CODE" >&2
  echo "  Check the nginx error log and storage/logs/laravel.log" >&2
  exit 1
fi

END=$(date +%s)
echo
echo "════════════════════════════════════════════"
echo "  Deploy complete in $((END - START))s"
echo "════════════════════════════════════════════"
