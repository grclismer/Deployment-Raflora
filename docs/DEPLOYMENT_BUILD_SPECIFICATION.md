# Raflora — Nixpacks & Railway Build Specification

## 1. Overview
Raflora is deployed using Railway with **Nixpacks** as the container image builder. 

This specification establishes an explicit, reproducible build and startup process that:
- Compiles production frontend assets using Vite 7 and Tailwind CSS 4.
- Installs production-only PHP dependencies via Composer.
- Separates build-time dependencies (Node.js) from runtime execution (PHP).
- Integrates persistent storage and symlink initialization via `scripts/container-startup.sh`.
- **Strictly excludes automatic database migrations** on container boot to prevent race conditions and data corruption.

---

## 2. Nixpacks Specification (`nixpacks.toml`)

The build specification is defined in `nixpacks.toml` at the repository root:

```toml
# Nixpacks Build & Startup Specification for Raflora
# Reference: https://nixpacks.com/docs/configuration/file

providers = ["node", "php"]

[variables]
NIXPACKS_NODE_VERSION = "20"
NIXPACKS_PHP_ROOT_DIR = "/app/public"

[phases.install]
cmds = [
    "composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader",
    "npm ci --include=dev"
]

[phases.build]
cmds = [
    "npm run build"
]

[start]
cmd = "sh scripts/container-startup.sh php artisan serve --host=0.0.0.0 --port=${PORT:-8080}"
```

---

## 3. Phase-by-Phase Technical Rationale

### A. Providers & Environment Variables
- `providers = ["node", "php"]`: Explicitly declares both Node.js (for frontend compilation) and PHP (for Laravel runtime).
- `NIXPACKS_NODE_VERSION = "20"`: Pins Node.js to version 20 LTS for predictable Vite compilation.
- `NIXPACKS_PHP_ROOT_DIR = "/app/public"`: Configures web root in case Nginx or alternative PHP providers are engaged.

### B. Install Phase (`phases.install`)
1. **Composer Installation:**
   `composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader`
   - Omits development packages (`phpunit`, `fakerphp`, `sail`, `mockery`).
   - Dumps an optimized class map for fast autoloader resolution.
   - **Crucial Distinction:** Does **NOT** execute `composer setup`. The repository's `composer.json` includes a `"setup"` script that executes `@php artisan migrate --force`. Avoiding `composer setup` is a required safety measure.
2. **NPM Installation:**
   `npm ci --include=dev`
   - Uses the exact versions frozen in `package-lock.json`.
   - Explicitly passes `--include=dev` to ensure `vite`, `@tailwindcss/vite`, `@tailwindcss/postcss`, and `laravel-vite-plugin` are installed even when `NODE_ENV=production` is set in the environment.

### C. Build Phase (`phases.build`)
- `npm run build`:
  - Executes `vite build`.
  - Compiles `resources/css/app.css` and `resources/js/app.js`.
  - Outputs `public/build/manifest.json` and hashed assets to `public/build/assets/`.
  - **Mandatory for Production:** Because `public/build` is ignored by Git, assets must be generated during container compilation. Without this step, Laravel throws `ViteManifestNotFoundException` upon page requests.

### D. Startup Command (`start`)
- `sh scripts/container-startup.sh php artisan serve --host=0.0.0.0 --port=${PORT:-8080}`:
  - Invokes Raflora's verified container startup script.
  - Automatically executes `php artisan storage:initialize`:
    - Ensures persistent storage directories (`storage/app/public`, `storage/app/private`, framework cache/sessions/views).
    - Validates or repairs the `public/storage` symbolic link.
    - Aborts immediately (`set -e`) if `public/storage` exists as a conflicting regular directory or file.
  - Dynamically binds to Railway's assigned port (`${PORT:-8080}`) across `0.0.0.0`.
  - Hands execution off via `exec "$@"`, ensuring the web server receives process signals (SIGTERM/SIGINT) directly.

---

## 4. Strict Database Migration Isolation Policy

> **WARNING: DATABASE MIGRATIONS MUST NEVER BE RUN AUTOMATICALLY DURING WEB CONTAINER STARTUP.**

1. **Race Conditions in Horizontally Scaled Deployments:**
   When multiple web containers spin up simultaneously, running automatic migrations causes table locks, concurrency collisions, or incomplete transactions.
2. **Destructive Migration Risk:**
   Migration `database/migrations/2026_09_21_000002_add_is_bootstrap_and_create_admin_recovery_codes.php` contains data-altering logic in its `up()` method that resets the administrator's verification state (`email_verified_at = null`). Triggering migrations inadvertently on web boot risks invalidating active admin access.
3. **Controlled Migration Procedure:**
   Database migrations must be run as a separately reviewed, isolated administrative command via Railway CLI or a dedicated pre-deploy release phase:
   ```bash
   railway run php artisan migrate --force
   ```

---

## 5. Build vs. Runtime Separation

| Asset / Tool | Needed During Build? | Needed During Runtime? | Notes |
| :--- | :---: | :---: | :--- |
| **Node.js & npm** | **YES** | **NO** | Needed only to compile Vite assets. |
| **node_modules** | **YES** | **NO** | Not required at runtime once assets are compiled into `public/build`. |
| **PHP 8.2+** | **YES** | **YES** | Required for Composer install and Laravel runtime. |
| **Vite Manifest** | **YES** | **YES** | `public/build/manifest.json` is read by Laravel PHP at runtime. |
| **Composer Dependencies** | **YES** | **YES** | Vendor autoload directory is required to execute the application. |

---

## 6. Railway Configuration Checklist

When configuring the Railway service:
- **Build Type:** Nixpacks (reads `nixpacks.toml` automatically).
- **Environment Variables:**
  - `APP_ENV=production`
  - `APP_KEY=base64:...`
  - `APP_DEBUG=false`
  - `APP_URL=https://your-service.up.railway.app`
  - `TRUSTED_PROXIES=*`
  - `DB_CONNECTION=mysql` (or `pgsql` / `sqlite`)
  - `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- **Volume Mount:** Mount persistent volume to `/app/storage/app`.

---

## 7. Remaining Unverified Items (Deployment Stage)
The following behaviors cannot be proven locally and require observation during actual Railway deployment:
- Live verification of Nixpacks provider package resolution on Railway's build cluster.
- Real port binding and health check detection behind Railway's internal edge proxy.
- File permissions of Railway attached volume drivers under container runtime user.
