#!/usr/bin/env bash
# Soscom SMS production helper for shared hosting with SSH.
#
# GitHub Actions uploads a tar.gz over SSH (this host has no rsync), then runs:
#   bash scripts/deploy.sh
#
# First-time database seed (creates the admin user):
#   bash scripts/deploy.sh --seed
#
# One cron (every minute) is enough. Deploy installs it when crontab is available:
#   * * * * * cd /home/USER/soscomsms && bash scripts/deploy.sh schedule >/dev/null 2>&1
# That runs Laravel's scheduler, which drains the database queue and other minute jobs.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

if [[ -n "${PHP_BIN:-}" ]]; then
  PHP="$PHP_BIN"
elif command -v php >/dev/null 2>&1; then
  PHP="$(command -v php)"
else
  echo "[deploy] php was not found on PATH. Set PHP_BIN to your cPanel PHP 8.2+ binary."
  exit 1
fi

ARTISAN=("$PHP" artisan)
MODE="${1:-deploy}"

log() {
  echo "[deploy] $*"
}

require_env_file() {
  if [[ ! -f "$ROOT/.env" ]]; then
    log "Missing .env. Copy .env.production.example to .env on the server and fill in secrets before deploying."
    exit 1
  fi
}

ensure_writable_dirs() {
  mkdir -p \
    storage/app/public \
    storage/app/private \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

  chmod -R ug+rwx storage bootstrap/cache 2>/dev/null || true
}

php_major_minor() {
  "$PHP" -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;'
}

run_schedule() {
  require_env_file
  "${ARTISAN[@]}" schedule:run
}

run_queue() {
  log "Do not run a long-lived queue:work on this host."
  log "Jobs are drained every minute by: bash scripts/deploy.sh schedule"
}

cron_line() {
  echo "* * * * * cd ${ROOT} && PHP_BIN='${PHP}' bash ${ROOT}/scripts/deploy.sh schedule >> ${ROOT}/storage/logs/cron.log 2>&1"
}

is_production_env() {
  grep -qE '^APP_ENV=production[[:space:]]*$' "$ROOT/.env"
}

ensure_cron() {
  require_env_file

  if ! is_production_env; then
    log "Skipping crontab install because APP_ENV is not production."
    return 0
  fi

  local line
  line="$(cron_line)"

  if ! command -v crontab >/dev/null 2>&1; then
    log "crontab is not available. Add this line in cPanel:"
    log "$line"
    return 0
  fi

  local current
  current="$(crontab -l 2>/dev/null || true)"
  if printf '%s\n' "$current" | grep -F 'scripts/deploy.sh schedule' >/dev/null; then
    log "Cron already runs schedule:run every minute."
    return 0
  fi

  {
    printf '%s\n' "$current"
    printf '%s\n' "$line"
  } | crontab -
  log "Installed one cron for schedule:run."
}

drain_queue_once() {
  log "Draining queued jobs once."
  "${ARTISAN[@]}" sms:drain-queue --max-time=30 --no-interaction || true
}

run_deploy() {
  local seed=0
  if [[ "$MODE" == "--seed" || "${SEED:-0}" == "1" ]]; then
    seed=1
  fi

  require_env_file
  ensure_writable_dirs

  local version
  version="$(php_major_minor)"
  if [[ "$version" != 8.2 && "$version" != 8.3 && "$version" != 8.4 && "$version" != 8.5 ]]; then
    log "PHP $version detected. Laravel 12 needs PHP 8.2 or newer. Switch the cPanel MultiPHP version for this domain and for SSH/cron."
    exit 1
  fi

  if ! grep -qE '^APP_KEY=base64:' .env; then
    log "APP_KEY is empty. Generating one."
    "${ARTISAN[@]}" key:generate --force --no-interaction
  fi

  if [[ "${COMPOSER_ON_SERVER:-0}" == "1" ]]; then
    if command -v composer >/dev/null 2>&1; then
      log "Installing PHP dependencies on the server."
      composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
    else
      log "COMPOSER_ON_SERVER=1 but composer is not installed."
      exit 1
    fi
  fi

  if [[ ! -f vendor/autoload.php ]]; then
    log "vendor/ is missing. GitHub Actions should upload it. If this host has Composer, re-run with COMPOSER_ON_SERVER=1."
    exit 1
  fi

  if [[ ! -f public/build/manifest.json ]]; then
    log "public/build/manifest.json is missing. Frontend assets must be built with npm run build before this script runs."
    exit 1
  fi

  log "Putting the app into maintenance mode."
  "${ARTISAN[@]}" down --retry=60 --no-interaction || true

  log "Running database migrations."
  "${ARTISAN[@]}" migrate --force --no-interaction

  if [[ "$seed" -eq 1 ]]; then
    log "Seeding database (first-time only)."
    "${ARTISAN[@]}" db:seed --force --no-interaction
  fi

  "${ARTISAN[@]}" storage:link --force --no-interaction >/dev/null 2>&1 || "${ARTISAN[@]}" storage:link --no-interaction >/dev/null 2>&1 || true

  log "Rebuilding Laravel caches."
  "${ARTISAN[@]}" optimize:clear --no-interaction
  "${ARTISAN[@]}" config:cache --no-interaction
  "${ARTISAN[@]}" view:cache --no-interaction
  "${ARTISAN[@]}" event:cache --no-interaction || log "event:cache skipped."
  "${ARTISAN[@]}" route:cache --no-interaction || log "route:cache skipped."

  log "Signalling queue workers to restart."
  "${ARTISAN[@]}" queue:restart --no-interaction

  log "Clearing scheduler locks left by killed cron runs."
  "${ARTISAN[@]}" schedule:clear-cache --no-interaction || true

  ensure_writable_dirs

  log "Bringing the app back up."
  "${ARTISAN[@]}" up --no-interaction

  ensure_cron
  drain_queue_once

  log "Cancelling stuck unsent recipients and releasing due schedules."
  "${ARTISAN[@]}" sms:dispatch-scheduled --no-interaction || true

  log "Production deploy finished."
}

case "$MODE" in
  schedule)
    run_schedule
    ;;
  queue)
    run_queue
    ;;
  cron)
    ensure_cron
    ;;
  deploy|--seed)
    run_deploy
    ;;
  *)
    echo "Usage: bash scripts/deploy.sh [deploy|--seed|queue|schedule|cron]"
    exit 1
    ;;
esac
