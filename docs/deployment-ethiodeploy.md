# Deploying CRO Intelligence to EthioDeploy (free plan)

This guide is written against the **verified** free-plan behaviour recorded in
[hosting-constraints.md](hosting-constraints.md). Where a step depends on
something the free plan does not provide, it says so instead of assuming it.

---

## 0. What the free plan gives you (and what it does not)

| Provided | Not provided |
|---|---|
| Deploy from a GitHub repository | SSH / shell access |
| A managed PHP runtime (build + start commands) | Cron jobs |
| A managed MySQL database | Redis / Memcached |
| Environment variables via the dashboard | Persistent background workers |
| Automatic HTTPS + a subdomain | Docker |
| Build logs and runtime logs in the dashboard | Guaranteed always-on instance (free apps sleep) |

**Consequences for this app — already designed in:**

- **No queue worker.** Scans run synchronously inside the request. The crawl is
  capped at 10 pages / depth 2 / 10 s per request so it finishes in time.
- **No cron.** Nothing in the app requires a scheduled task. Abandoned scans are
  reaped lazily on the next scan request, not by a scheduler.
- **Sleeping instances.** The first request after a sleep is slow. The app has
  no long-lived in-memory state, so a cold start is safe.
- **No Redis.** `CACHE_STORE=file`, `SESSION_DRIVER=file`, `QUEUE_CONNECTION=sync`.

---

## 1. Push the code to GitHub

```bash
cd cro-intelligence
git init
git add .
git commit -m "CRO Intelligence: initial working build"

# create an empty repo on GitHub first, then:
git remote add origin https://github.com/<you>/cro-intelligence.git
git branch -M main
git push -u origin main
```

`.gitignore` already excludes `.env`, `vendor/`, `node_modules/` and
`storage/logs/*`. **`public/build/` is committed on purpose** — the free plan
may not run `npm` during its build step, so the compiled CSS/JS must be in the
repository.

---

## 2. Create the app on EthioDeploy

1. Dashboard → **New Application** → **Import from GitHub**.
2. Authorise GitHub and pick the `cro-intelligence` repository, branch `main`.
3. Choose the **PHP** runtime. Select the highest PHP version offered that is
   **8.2 or newer** (Laravel 12 requires ≥ 8.2). If the platform only offers
   8.1, the app will not boot — do not proceed; use a host with PHP 8.2+.
4. Attach a **MySQL** database from the same dashboard. Copy its host, port,
   database name, user and password.

---

## 3. Build and start commands

Set these in the app's **Build & Deploy** settings.

**Build command**

```bash
composer install --no-dev --optimize-autoloader --no-interaction
```

**Start command**

```bash
php artisan migrate --force && php artisan config:cache && php artisan route:cache && php artisan view:cache && php -S 0.0.0.0:$PORT -t public
```

Notes:

- `migrate --force` runs on every deploy. It is idempotent — already-applied
  migrations are skipped. This is the only way to migrate without SSH.
- `php -S` is PHP's built-in server. It is single-threaded, which is acceptable
  here because scans are bounded and the app is low-traffic. If EthioDeploy
  offers a PHP-FPM/Apache runtime instead, use that and point the document root
  at `public/`.
- **The document root must be `public/`.** If the platform only allows a
  repository root, add a root `.htaccess` that rewrites everything into
  `public/` (see §7).

---

## 4. Environment variables

Paste these into the dashboard's environment section. Generate `APP_KEY` locally
with `php artisan key:generate --show` and paste the value.

```env
APP_NAME="CRO Intelligence"
APP_ENV=production
APP_KEY=base64:...            # from: php artisan key:generate --show
APP_DEBUG=false               # MUST be false in production
APP_URL=https://<your-app>.ethiodeploy.app

LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=<from dashboard>
DB_PORT=3306
DB_DATABASE=<from dashboard>
DB_USERNAME=<from dashboard>
DB_PASSWORD=<from dashboard>

SESSION_DRIVER=file
SESSION_LIFETIME=120
CACHE_STORE=file
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local

MAIL_MAILER=log               # no SMTP on the free plan; password reset links go to the log
```

**Optional AI layer** — leave these unset to run on the rule-based explainer:

```env
# AI_API_KEY=sk-...
# AI_BASE_URL=https://api.openai.com/v1
# AI_MODEL=gpt-4o-mini
```

> Password reset needs a mailer. On the free plan there is no SMTP, so
> `MAIL_MAILER=log` writes the reset link to `storage/logs/laravel.log`. To make
> reset emails real, add a free transactional provider (e.g. Brevo/Resend free
> tier) and set `MAIL_MAILER=smtp` with its credentials. This is a genuine
> external dependency, not something the app can fake.

---

## 5. First deploy checklist

1. Deploy. Watch the build log for `composer install` completing.
2. Watch the runtime log for `migrate` output — you should see the 16 tables
   created (plus Laravel's own).
3. Open `https://<your-app>.ethiodeploy.app` → you should be redirected to
   `/login`.
4. Register an account, create a project with a public URL, run a scan.
5. Confirm the scan completes and a PDF downloads.

If step 4 fails with a 500, check the runtime log. The most common causes are a
wrong `DB_*` value or a missing `APP_KEY`.

---

## 6. Storage permissions

Laravel writes to `storage/` and `bootstrap/cache/`. If the platform does not
set these writable, add to the **build command**:

```bash
mkdir -p storage/framework/{sessions,views,cache} storage/logs bootstrap/cache && chmod -R 775 storage bootstrap/cache
```

---

## 7. If the document root cannot be set to `public/`

Add this `.htaccess` at the repository root:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
```

And add a `public/.htaccess` (Laravel ships one) so front-controller routing
works. **Verify** by requesting `/login` — a 404 means the rewrite is not
active.

---

## 8. Protecting secrets and logs

The free plan serves from the repository root unless you set the document root
to `public/`. If you cannot, the `.htaccess` above is not enough on its own —
also confirm these paths return **403/404**, never file contents:

- `/.env`
- `/storage/logs/laravel.log`
- `/composer.json`, `/composer.lock`
- `/database/migrations/...`

Test each with `curl -I https://<your-app>/<path>`. If any returns `200`, stop
and fix the document root before putting real data in the app.

---

## 9. Updating the app

```bash
git add . && git commit -m "..." && git push
```

EthioDeploy redeploys on push. The start command re-runs `migrate --force`, so
new migrations apply automatically. Clear caches if a config change does not
appear: the start command already runs `config:cache` on every boot.

---

## 10. Known free-plan limits to plan around

- **Cold starts.** A sleeping app takes several seconds to answer the first
  request. Scans started immediately after a cold start may be slow.
- **Request timeout.** A scan must finish inside the platform's request limit.
  The crawler's wall-clock budget is set below typical limits, but a very slow
  target site can still time out. The scan is then marked `failed` with a clear
  message, and the next scan is not blocked.
- **No cron.** If you later add features that need periodic work, they must be
  triggered by a request or moved to a separate service.
- **No Redis.** File cache and file sessions are used. This is fine for a
  single instance; it does not work across multiple instances.
