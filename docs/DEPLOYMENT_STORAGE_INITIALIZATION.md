# Raflora — Container Startup and Persistent Storage Initialization

## 1. Overview
In containerized hosting environments (such as Railway or Docker), containers have ephemeral filesystems. Any files written inside the container are lost when the container stops, restarts, or deploys a new build.

To preserve client uploads, receipts, gallery images, and damage assessment evidence, persistent storage must be mounted and initialized on container boot.

---

## 2. Storage Directory Architecture

| Directory Path | Filesystem Disk | Accessibility | Description |
| :--- | :--- | :--- | :--- |
| `storage/app/public` | `public` | Public (via `/storage/...`) | Gallery images, event inspiration photos, payment receipts. |
| `storage/app/private` | `private` / `local` | Protected / Authenticated | Material return damage evidence, condition assessments. |
| `storage/framework/views` | N/A | Ephemeral runtime | Compiled Blade templates. |
| `storage/framework/sessions` | N/A | Ephemeral runtime | File-based sessions (if redis/database not used). |
| `storage/framework/cache` | N/A | Ephemeral runtime | Cache store and tags. |
| `storage/framework/cache/data` | N/A | Ephemeral runtime | File cache data partition. |
| `storage/logs` | N/A | Ephemeral runtime | Application logs (`laravel.log`). |

### Recommended Railway Volume Mount
- **Mount Path:** `/app/storage/app`
- Mounting at `/app/storage/app` persists both `public` and `private` storage disks across deployments without persisting ephemeral framework cache or logs across container rebuilds.

---

## 3. Storage Initialization Command (`php artisan storage:initialize`)

The custom command `app/Console/Commands/InitializeStorage.php` provides an automated, idempotent check:

```bash
php artisan storage:initialize
# Options:
#   --force : Recreates the public/storage symlink if stale or broken
```

### Safety & Guardrails:
1. **Directory Creation:** Ensures `storage/app/public`, `storage/app/private`, and all required `storage/framework/*` directories exist with `0775` permissions.
2. **Symlink Validation:**
   - Checks if `public/storage` points to `storage/app/public`.
   - If the symlink is missing, creates it via `php artisan storage:link`.
   - If the symlink is stale or broken, repairs it via `php artisan storage:link --force`.
   - **Protection against silent overwrite:** If `public/storage` exists as a **regular file or regular directory** (not a symbolic link or junction), the command **aborts with exit code 1**. It will never silently delete or overwrite user data.
3. **Cross-Platform Compatibility:** Supports Linux symlinks (`is_link()`) and Windows NTFS Junctions created by `mklink /J`.
4. **Private Storage Isolation:** Asserts that `storage/app/private` is not located within `public/storage`.

---

## 4. Container Startup Script (`scripts/container-startup.sh`)

POSIX-compliant shell script for use as a container entrypoint or startup prefix:

```sh
#!/bin/sh
set -eu

# Runs storage:initialize before launching the web server or worker.
# Fails immediately if initialization fails.
php artisan storage:initialize

# Executes application command passed via arguments
exec "$@"
```

### Railway Usage
In `railway.json` or Railway Service Settings:
- **Custom Start Command:**
  ```bash
  sh scripts/container-startup.sh php artisan serve --host=0.0.0.0 --port=$PORT
  ```
  *(Or with an Nginx/PHP-FPM container: `sh scripts/container-startup.sh /entrypoint.sh`)*

---

## 5. Critical Database Migration Isolation Policy

> **WARNING: DO NOT RUN DATABASE MIGRATIONS IN CONTAINER STARTUP SCRIPTS.**

Automatic migration execution (`php artisan migrate` or `migrate:fresh`) on web container boot is strictly forbidden:
1. **Multi-Instance Hazard:** In multi-container or horizontally scaled deployments, multiple containers starting simultaneously can cause database migration race conditions and deadlocks.
2. **Destructive Migration Risk:** Raflora's migration `2026_09_21_000002_add_is_bootstrap_and_create_admin_recovery_codes.php` contains data-altering statements in its `up()` method that reset the admin's email verification state (`email_verified_at = null`). Running migrations inadvertently during web container startup risks disrupting production admin credentials.
3. **Operational Standard:** Migrations must always be executed as a controlled release phase or one-off administrative task (`railway run php artisan migrate`).

---

## 6. Local Development Compatibility
- The `storage:initialize` command detects Windows NTFS junction points and operates cleanly on local development environments without disrupting existing links or test suites.
- The initialization command is fully covered by automated regression tests in `tests/Feature/StorageInitializationCommandTest.php`.

---

## 7. Remaining Deployment Limitations
The following items remain unverified until actual Railway deployment:
- Live verification of Railway persistent volume permissions (Nixpacks container user `uid=1000` vs storage directories).
- Real HTTP asset delivery verification behind Railway reverse proxy via `/storage/...`.
