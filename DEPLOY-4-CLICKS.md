# Your 4 clicks — CRO Intelligence on EthioDeploy

Everything is already built, tested and committed. This is the whole job:
**4 clicks, then paste some values.** Budget: about 15 minutes.

Do them in order. Each step has a **Check** — if the check fails, stop and
send me what it says (see the last section).

---

## Click 1 — Create the empty GitHub repo

1. Open <https://github.com/new>
2. Name: `cro-intelligence`
3. Visibility: **Private**
4. **Leave every checkbox unticked** (no README, no .gitignore, no license)
5. Click **Create repository**

**Check:** the page says *"Quick setup — if you've done this kind of thing
before"* and shows an empty repo. If it shows any files, the repo is not empty
— delete it and start again.

> Then push your code up. Full commands are in `PUSH-INSTRUCTIONS.md`, but in
> short: `git clone cro-intelligence.bundle cro-intelligence`, then
> `git remote add origin https://github.com/<you>/cro-intelligence.git`,
> then `git push -u origin main`.

**Check:** GitHub shows **182 files** and one commit on branch `main`.

---

## Click 2 — Connect the repo in EthioDeploy

1. EthioDeploy dashboard → **New Application**
2. Choose **Import from GitHub**
3. Authorise GitHub if asked, then pick `cro-intelligence`, branch `main`
4. Runtime: **PHP**. Pick the highest version offered that is **8.2 or newer**
   (Laravel 12 needs ≥ 8.2). If only 8.1 is offered, stop — it will not boot.
5. Click **Create / Deploy**

**Check:** the app is created and a build starts. It will fail on the first
run because there is no database yet — that is expected. Move to click 3.

---

## Click 3 — Add the MySQL addon

1. In the app, open the **Add-ons** / **Resources** tab
2. Add **MySQL** (the free tier)
3. Wait for it to provision, then open its details

**Check:** you can see a connection string that looks like
`mysql://user:password@host:3306/dbname`. EthioDeploy injects this into your
app automatically as `DATABASE_URL` — **you do not need to copy it anywhere.**

> If your dashboard instead shows separate fields (host, port, database, user,
> password), note them down — you will paste them as `DB_*` in click 4.

---

## Click 4 — Paste the environment variables

App → **Settings** → **Environment Variables**. Add these **exactly**:

| Key | Value |
|---|---|
| `APP_NAME` | `CRO Intelligence` |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | your live URL, e.g. `https://cro-intelligence.ethiodeploy.com` — **no trailing slash** |
| `APP_KEY` | see below |
| `LOG_LEVEL` | `error` |
| `SESSION_DRIVER` | `database` |
| `CACHE_STORE` | `file` |
| `QUEUE_CONNECTION` | `sync` |
| `DB_CONNECTION` | `mysql` |

**Getting `APP_KEY`** — run this on your own machine, in the project folder:

```bash
php artisan key:generate --show
```

It prints one line like `base64:9xK2...=`. Paste that **whole line** as the
value of `APP_KEY`. Do not add quotes.

**Only if click 3 gave you separate DB fields instead of a URL**, also add:

| Key | Value |
|---|---|
| `DB_HOST` | the host from the addon |
| `DB_PORT` | `3306` |
| `DB_DATABASE` | the database name |
| `DB_USERNAME` | the user |
| `DB_PASSWORD` | the password |

**Leave these blank / unset** — the app works without them:
`AI_API_KEY`, `AI_BASE_URL`, `AI_MODEL`. With no key it uses the built-in
rule-based explainer. No paid service, no credit card.

**Check:** all ten rows above are present, `APP_DEBUG` is `false`, and
`APP_KEY` starts with `base64:`.

---

## Then: set the Start command

App → **Settings** → **Build & Deploy** → **Start command**:

```bash
php artisan migrate --force && ethiodeploy-start
```

- `migrate --force` creates all 25 tables on first boot and is safe to re-run
  on every deploy (already-applied migrations are skipped).
- `ethiodeploy-start` must stay **last** — nothing after it runs.

**Leave the Build command empty.** The platform runs `composer install` and
`npm run build` itself.

**You do not set a document root.** EthioDeploy points nginx at `public/`
automatically for a Laravel app. That is also why `/.env` and `/storage/logs`
are not reachable from the browser.

Now click **Deploy** (or **Redeploy**).

---

## Verify it actually works

Open your `APP_URL`. Then check these five things:

1. **The landing page loads and is styled** — dark navy sidebar, not plain
   unstyled HTML. Unstyled means the asset URLs are wrong; check `APP_URL`.
2. **`/register` works** — create an account. If you get a 500, the database
   is not connected; re-check click 3/4.
3. **`/up` returns OK** — this is Laravel's health endpoint.
4. **Add a project and run a scan** on `https://example.com`. It should finish
   in under 15 seconds with a score and a list of findings.
5. **Download the PDF report** — it should open as a real PDF.

Optional — create the demo account. Temporarily set the Start command to:

```bash
php artisan migrate --force && php artisan db:seed --force && ethiodeploy-start
```

Deploy once, confirm you can log in as `demo@cro.test`, then **change it back**
to the line above. Seeding on every boot would reset data.

---

## If something fails — send me this

Copy the **exact error text** and tell me which step you were on. The most
useful things:

- **The build log** — EthioDeploy → your app → **Deployments** → the failed
  build → copy the last ~30 lines.
- **The runtime log** — same app → **Logs** tab.
- **A screenshot** of the error page or the failing dashboard step.
- **Which check failed** — e.g. "step 4, page loads but has no styling".

Do **not** paste your `APP_KEY`, database password or the full
`DATABASE_URL` — I do not need them, and they should stay private.

### Quick diagnosis

| What you see | Likely cause | Fix |
|---|---|---|
| Page loads but completely unstyled | `APP_URL` wrong, or assets not built | Set `APP_URL` to the exact https URL, no trailing slash; redeploy |
| `500 Server Error` on every page | `APP_KEY` missing or malformed | Re-paste the full `base64:...` line |
| `could not find driver` | PHP missing `pdo_mysql` | Tell me — the runtime needs a different PHP build |
| `SQLSTATE[HY000] [2002]` | Database not reachable | Re-check the MySQL addon is attached and `DATABASE_URL` exists |
| `Access denied for user` | Wrong DB credentials | Re-copy the addon values |
| `Table 'xxx.sessions' doesn't exist` | Migrations did not run | Confirm the Start command is exactly as above, then redeploy |
| Logged out after every deploy | `SESSION_DRIVER` is `file` | Set it to `database` |
| Scan fails with a network error | Host blocks outbound HTTP | Tell me — the crawler needs outbound access |
| App takes ~20s on first visit | Free plan sleeps when idle | Expected; it wakes on the first request |

---

## What you are deploying

A real Laravel 12 + MySQL application: authentication, project management, a
PHP crawler with SSRF protection, 86 CRO rules across 7 categories, a weighted
scoring engine, opportunity prioritisation, scan history with deltas, PDF
reports, and an optional AI layer that falls back to rules when no key is set.

**67 tests / 936 assertions pass.** The `DATABASE_URL` fallback and the
proxy/HTTPS asset fix were both verified against a real MySQL database before
this was committed.

**Known limits on the free plan:** no queue worker (scans run inline, capped at
10 pages), no cron, no SMTP (password-reset emails go to the log), and the app
sleeps when idle. Browser rendering, Lighthouse and axe-core are not included —
they need a separate service.
