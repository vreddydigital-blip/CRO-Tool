# CRO Intelligence

A Laravel + MySQL SaaS that crawls a website from its URL and produces a
**heuristic conversion-rate-optimisation (CRO) assessment**: findings with
evidence, prioritised recommendations, category scores, a transparent overall
score, scan history and downloadable PDF reports.

> **What this is not.** It is not a conversion rate, and it does not predict or
> guarantee conversion uplift. It is a rule-based assessment of publicly
> visible, server-rendered HTML. See [Limitations](#7-limitations).

---

## 1. What actually works (verified)

Everything below was executed and observed in a real environment, not merely
generated. Verification method is stated for each item.

| Capability | Status | How it was verified |
|---|---|---|
| Registration, login, logout, password reset, profile | Working | 6 feature tests + live HTTP session |
| Per-user project ownership isolation | Working | 5 feature tests; live second user received **403** on another user's project, scan and report |
| SSRF protection on user-supplied URLs | Working | 16 unit tests; live POST of `http://127.0.0.1:8899/admin` was rejected and created no project |
| PHP crawler (robots.txt, sitemap, link discovery, dedupe, depth/page caps) | Working | Live crawl of `https://example.com`; crawl logs persisted |
| CRO rule engine | Working | **86 unique rules** across 7 categories; 4 tests assert count, uniqueness, metadata completeness and category coverage |
| CTA / form / trust analysers | Working | Exercised by the live scan; findings produced with evidence |
| Scoring engine (weighted, 0–100) | Working | 5 unit tests incl. weight sum = 100 and bounds |
| Opportunity prioritisation | Working | 4 unit tests on the formula and label thresholds |
| Findings dashboard, filters, evidence view | Working | Live HTTP 200; rendered content confirmed |
| Recommendations view | Working | Live HTTP 200; 25 recommendations generated for the test scan |
| Scan history with score deltas | Working | Live HTTP 200 |
| PDF report (Dompdf) | Working | Live download returned `application/pdf`, 934,967 bytes, `%PDF-` header |
| AI explanation layer with rule-based fallback | Working | Live: provider resolved to `RuleBasedFallback`, returned text; app runs with **no API key** |
| Competitor comparison (up to 2) | Working | Live HTTP 200; comparison table renders |
| Analytics integration interfaces (GA4/GTM/CRM) | **Interface only** | `AnalyticsProvider` contract + `NotConnectedProvider`. No driver implemented; reports "not connected" and never fabricates data |
| Browser rendering / Lighthouse / axe-core | **Not implemented** | Marked as a separate optional service (Phase 6) |

**Test suite: 67 tests, 936 assertions, all passing.**

---

## 2. Requirements

- PHP **8.2+** (developed and tested on PHP 8.2; Laravel 12 requires ≥ 8.2)
- MySQL **8** (or MariaDB 10.4+)
- Composer 2
- Node.js 20 + npm (build-time only — the compiled assets are committed)

> **Note on Laravel version.** The brief asked for Laravel 11. Composer refuses
> to install Laravel 11 because of published security advisories affecting it.
> **Laravel 12** was used instead: it supports the same PHP 8.2 runtime and is
> the secure choice. No application code depends on version-specific behaviour.

---

## 3. Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate

# Create the database, then set DB_* in .env
mysql -u root -e "CREATE DATABASE cro_intelligence CHARACTER SET utf8mb4;"

php artisan migrate --seed     # --seed loads safe demo data
npm install && npm run build   # only needed if you change CSS/JS
php artisan serve
```

Demo login after seeding: `demo@cro.test` / `password123`.

### Running the tests

Tests use a **separate MySQL database** (configured in `phpunit.xml`), because
the sandbox has no SQLite driver:

```bash
mysql -u root -e "CREATE DATABASE cro_testing CHARACTER SET utf8mb4;"
php artisan test
```

---

## 4. Architecture

```
app/
├── Http/Controllers/        Dashboard, Project, Scan, Finding, Recommendation,
│                            Report, Competitor, Settings, ScanHistory
├── Models/                  16 Eloquent models
├── Policies/                ProjectPolicy, ScanPolicy (ownership)
└── Services/
    ├── Security/            UrlValidator, SafeHttpClient   ← SSRF defence
    ├── Crawler/             SiteCrawler                    ← bounded crawl
    ├── Analysis/            RuleLibrary (86 rules), PageContext, Rule,
    │                        ClosureRule, CtaAnalyzer, FormAnalyzer,
    │                        TrustAnalyzer, ScoringEngine, Priority, ScanRunner
    ├── Ai/                  AiProvider, OpenAiCompatibleProvider,
    │                        RuleBasedFallback, AiManager
    ├── Reports/             ReportService (Dompdf)
    └── Integrations/        AnalyticsProvider, NotConnectedProvider,
                             AnalyticsManager
```

### Scan pipeline

```
URL → UrlValidator (SSRF) → SiteCrawler → PageContext per page
    → RuleLibrary (86 rules) → findings + evidence
    → ScoringEngine (weighted) → category + overall score
    → Priority (Impact×Confidence×Reach ÷ Effort) → recommendations
    → AiManager (AI or rule-based) → PDF report
```

Scans run **synchronously inside the HTTP request** that starts them. There is
no queue worker, because the free hosting tier provides no persistent process.
The crawl is bounded (10 pages, depth 2, 10 s per request, 2 MB per page, hard
wall-clock budget) so a scan finishes inside a normal request.

---

## 5. Scoring methodology

Overall score = weighted sum of seven category scores, each 0–100.

| Category | Weight |
|---|---|
| Value proposition | 20% |
| CTA effectiveness | 20% |
| Trust & credibility | 15% |
| UX & navigation | 15% |
| Mobile usability | 15% |
| Content clarity | 10% |
| Technical readiness | 5% |

Each category starts at 100 and is reduced by the severity of the findings
detected in it. The scan also reports **confidence** (how many pages were
analysed) and **evidence coverage** (how much of the site was inspected), so a
low-confidence score is visibly low-confidence rather than silently wrong.

**Priority** = (Impact × Confidence × Reach) ÷ Effort, each factor 1–5.
Labels: ≥40 critical, ≥25 high, ≥12 medium, else low.

---

## 6. Security

- **SSRF protection** — `UrlValidator` rejects non-http(s) schemes, credentials
  in the URL, non-standard ports, `localhost`, `.internal`/`.local` names, and
  every private, loopback, link-local and cloud-metadata address (IPv4 and
  IPv6). `SafeHttpClient` **re-validates every redirect hop**, so a public URL
  cannot bounce the crawler to an internal address.
- **Ownership** — every project, scan, finding and report is authorised through
  a policy; a second user receives 403.
- **CSRF** on all state-changing routes; **XSS** escaped by Blade; **SQL
  injection** prevented by Eloquent/query-builder bindings.
- **Rate limiting** — 10 scans per hour per user, plus one concurrent scan.
- **Credentials** — API keys are read from the environment only. The
  `integrations.credentials` column is cast to `encrypted:array` and hidden
  from serialisation.
- **Production** — set `APP_DEBUG=false`. Never expose `.env`, `storage/logs`
  or source through a public path (see the deployment guide).

---

## 7. Limitations

Stated plainly, because the product must not overstate what it measured:

1. **Not a conversion rate.** The score is a heuristic assessment of visible
   page elements. It is not measured conversion performance and does not
   predict uplift. Validate any change with a controlled experiment.
2. **Server-rendered HTML only.** Content injected by JavaScript is not seen.
   A React/Vue SPA may score poorly for reasons that do not reflect its real
   rendered state.
3. **External stylesheets are not fetched.** Media queries and contrast defined
   in linked CSS are not visible to the mobile/accessibility checks. Rules that
   depend on them say so in their own `limitations` field.
4. **No visual assessment.** Layout quality, imagery and rendered contrast are
   not judged.
5. **No performance measurement.** Lighthouse/Core Web Vitals are not run.
6. **Competitor comparison is public-page only.** Competitor conversion rates,
   revenue and private analytics are unknowable and are never claimed.
7. **Analytics integrations are interfaces only.** No GA4/GTM/CRM driver ships.
   The app reports "not connected" rather than showing fabricated numbers.
8. **Free-tier bounds.** 10 pages, depth 2, one scan at a time, 10 scans/hour.
9. **No background workers.** A scan occupies its HTTP request; a very slow
   site can hit the request timeout. Abandoned scans are reaped automatically
   after 5 minutes so they cannot lock a user out.

---

## 8. Deployment

See **[docs/deployment-ethiodeploy.md](docs/deployment-ethiodeploy.md)** for the
EthioDeploy-specific guide, and
**[docs/hosting-constraints.md](docs/hosting-constraints.md)** for the verified
free-plan limits this app was designed around.

---

## 9. Licence

Proprietary. Built as a deliverable project.
