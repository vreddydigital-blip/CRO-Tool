# EthioDeploy — verified hosting constraints

Source: EthioDeploy's own documentation, read on 2026-11-14
(`/deploy/laravel`, `/free-backend-hosting`, `/pricing`, `/docs`).

Everything below is what the platform states. Anything not stated is marked
**unverified** and the app is designed not to depend on it.

## Free plan — what you actually get

| Resource | Free plan |
|---|---|
| Price | $0, no credit card |
| Projects | **1** |
| URL | `<project-slug>.ethiodeploy.app` over HTTPS |
| Memory | **256 MB** |
| CPU | **0.5 core** |
| Database addon | Postgres, MySQL **or** Redis — **512 MB**, does not expire |
| Deploys | On every push to the default branch, zero-downtime |
| Custom domain | **No** (Pro only) |
| Branch previews | **No** (Pro only) |
| Sleep | **Stops after 30 min without traffic**; next request wakes it, held up to 30 s |
| Files on disk | **Wiped on every deploy** |
| Support | Telegram community group |

## Build & runtime model (Laravel)

- Detected from `artisan` + `laravel/framework` in `composer.json`.
- Built on the official `php:X.Y-fpm` image with nginx + PHP-FPM. **No Dockerfile, no nginx config.**
- PHP version comes from `require.php` in `composer.json`. Supported: **8.2 – 8.5**; 8.4 preferred when the constraint allows.
- Always-installed extensions: `bcmath, intl, opcache, pcntl, pdo_mysql, pdo_pgsql, zip`. Anything declared as `ext-*` in `composer.json` is added.
- If `package.json` has a `build` script it runs on **Node 22** after `composer install`, output to `public/build`.
- On start: `storage:link`, `config:cache`, `route:cache`, `view:cache`, then PHP-FPM + nginx with `public/` as web root.
- Logs go to stderr (`LOG_CHANNEL=stderr`).
- `APP_KEY` is generated on first deploy and persisted if not set.
- Config is cached **after** env vars load, so a changed env var takes effect on the next deploy.

## What this means for the architecture

| Constraint | Design decision |
|---|---|
| No persistent worker; `queue:work` stops when the app sleeps | **`QUEUE_CONNECTION=sync`.** Scans run synchronously inside the HTTP request. No queued jobs anywhere in the product. |
| 30 s wake-up hold, request timeouts | Crawl is hard-bounded: **10 pages, depth 2, 45 s wall-clock budget, 10 s per request, 2 MB per page.** |
| 256 MB RAM | Pages are parsed one at a time and released. Raw HTML is **not** stored in the DB (only metadata + a hash); analysis re-fetches each page once. |
| 512 MB database | No raw HTML blobs. Findings are chunked inserts. Reports are generated on demand, not stored per scan. |
| Disk wiped on deploy | **No user uploads, no stored PDFs.** PDFs stream to the browser. `storage/` is treated as ephemeral. |
| 1 project only | Single-tenant deployment. Multi-tenant is a code-level capability, not a hosting claim. |
| No cron | No scheduled tasks. Nothing in the product depends on `schedule:run`. |
| No SSH | Migrations run via the **Start command** (`php artisan migrate --force && ethiodeploy-start`), not a shell. |
| No Redis assumed | `CACHE_STORE=database`, `SESSION_DRIVER=database`, `QUEUE_CONNECTION=sync`. Redis is optional, never required. |

## Unverified — do not depend on

- SSH access, shell access, cron, persistent workers, Docker, Redis availability.
- Outbound HTTP from the container to arbitrary hosts. **The crawler needs this.** If EthioDeploy blocks egress, scans fail with a clear error rather than silently returning empty results — see `ScanRunner` error handling.
- Any per-request CPU-time limit beyond the 30 s wake hold.

## Features that need infrastructure the free plan does not have

These are implemented behind interfaces and clearly marked in the UI as
requiring a separate service. They are **never** faked:

| Feature | Why it needs more | Status |
|---|---|---|
| JavaScript-rendered crawling (Playwright) | Needs a browser binary + ~1 GB RAM | Interface only (`BrowserRenderer` contract), not wired |
| Lighthouse performance audit | Needs Node + Chrome | Not implemented; performance rules use server-measured timings only |
| axe-core accessibility audit | Needs a browser | Not implemented; accessibility rules use static HTML checks only |
| GA4 / GTM / CRM sync | Needs OAuth + a token store + scheduled sync | Schema + interface only; no fabricated data |
| Conversion prediction | Needs real historical visitor + conversion data | Explicitly a future module; not implemented |
| AI recommendations | Needs an external API key | Optional; rule-based fallback always works with no key |
