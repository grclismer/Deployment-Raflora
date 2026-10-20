#!/bin/sh
set -eu

echo "==> [Raflora Startup] Starting persistent storage initialization..."

# Run the idempotent storage initialization command.
# If directory creation fails or public/storage is an invalid non-symlink path,
# this command exits with a non-zero code, halting startup immediately.
php artisan storage:initialize

echo "==> [Raflora Startup] Storage initialization successful."

# CRITICAL DEPLOYMENT ARCHITECTURE NOTE:
# Automatic database migrations ('php artisan migrate') are deliberately NOT run here.
# 1. Multi-instance / horizontal scaling can cause migration lock contention or race conditions.
# 2. Existing migration '2026_09_21_000002_add_is_bootstrap_and_create_admin_recovery_codes'
#    contains destructive data modifications in its up() method (resets admin verification state).
# Migrations must always be executed as a dedicated, controlled release phase or manual task.

# Scheduled alerts (tiered inventory shortage checks, expired quotations, price reconfirmation,
# expired guest request cleanup) only execute while the Laravel scheduler runs.
# Set RAFLORA_RUN_SCHEDULER=false when a dedicated scheduler service or cron entry
# (`php artisan schedule:run` every minute) is used instead. Tasks use onOneServer(),
# so running the scheduler on several instances does not duplicate alerts.
if [ "${RAFLORA_RUN_SCHEDULER:-true}" != "false" ]; then
    echo "==> [Raflora Startup] Starting Laravel scheduler in the background (php artisan schedule:work)."
    php artisan schedule:work >> storage/logs/scheduler.log 2>&1 &
fi

if [ "$#" -gt 0 ]; then
    echo "==> [Raflora Startup] Launching application command: $*"
    exec "$@"
else
    echo "==> [Raflora Startup] No application process specified. Initialization complete."
fi
