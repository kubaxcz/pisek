# Piskari — Sandstone Climbing Browser

## 1. Overview

A web application that builds and presents a structured catalogue of the
Czech sandstone-climbing guidebook **[piskari.cz](https://www.piskari.cz)**.

It consists of three parts:

- **Scraper** — crawls piskari.cz top-down and stores areas, sectors,
  rocks/towers and routes into the database.
- **Backend** — a PHP REST API serving the stored data to the frontend.
- **Frontend** — a React UI for browsing sectors, rocks and routes.

Domain concepts are Czech; **all code, comments and identifiers are in
English**.

---

## 2. Technology Stack

- **Backend:** plain PHP 8.2 (no heavy framework), PSR-4 autoload,
  REST-style JSON API, PDO + prepared statements.
- **Database:** MariaDB (utf8mb4 / `utf8mb4_unicode_ci`).
- **Frontend:** React (TypeScript) built with Vite, routing via React
  Router.
- **Source control:** GitHub.
- **Deployment:** GitHub Actions → build + test → deploy via SFTP.

The project layout and conventions mirror the sibling project
`/Users/kuba/projects/monkey-cup`:

```
piskari/
  backend/        PHP API (PSR-4 "Piskari\\" => src/)
    public/index.php   front controller / router
    src/Db, src/Http, src/Config, src/<Feature>...
    db/queries/        optional .sql query files
    composer.json      scripts: test (phpunit), lint (php-cs-fixer)
  frontend/       React + TypeScript app
  db/             numbered schema migrations (001_*.sql ...) + setup scripts
  scraper/        the crawler (PHP CLI, shares backend src/ + Db)
  .github/workflows/ci-build-deploy.yml
  specification.md
```

The scraper is implemented in PHP as a CLI command so it can reuse the
backend's `Db` layer and data model.

---

## 3. Data Hierarchy

```
Area (oblast)            e.g. Křížový vrch, Adršpach
  └─ Sector (obvod)      e.g. Jižní věže, Himálaj
       └─ Rock (skála)   e.g. Oslík  (a tower / rock formation)
            └─ Route (cesta)  an individual climbing line
```

---

## 4. Scraper

### 4.1 Areas (entry points)

The crawl starts from a configured list of area pages. Initial set:

| Area          | Entry URL                                  |
|---------------|--------------------------------------------|
| Křížový vrch  | `https://www.piskari.cz/cs/krizovy-vrch/`  |
| Adršpach      | `https://www.piskari.cz/cs/adrspach/`      |

More area entry URLs must be addable via config.

### 4.2 Sectors (obvody)

From each area page, extract the list of **sectors (obvody)**:

- **name** — e.g. `Jižní věže`, `Himálaj`
- **url** — absolute (hrefs are site-relative, e.g.
  `/cs/krizovy-vrch/jizni-veze-22/`, `/cs/adrspach/himalaj-1/`)
- **area** — parent area name
- **climbing season / restriction** — when climbing is allowed. Capture
  both forms found on the area page:
  - a season range string, e.g. `1.7. – 30.11.` or `1.5. – 30.11.`
  - a textual restriction status, e.g. `lezení bez omezení`
    (no restriction), `lezení s omezením` (restricted), or a note that
    a part is permanently closed.

### 4.3 Rocks / Towers (skály)

From each sector page, extract the list of **rocks (skály)**:

- **name** — e.g. `Oslík`
- **url** — absolute. Links use a name-slug + numeric-id form:
  `/cs/skala/oslik-2792/`. The numeric-only form `/cs/skala/a-2792/`
  also resolves (server redirect); store and follow the canonical slug
  URL as found in the link.

### 4.4 Rock detail page

For each rock, fetch its detail page and extract:

- **name**
- **area (oblast)** — e.g. `Křížový vrch`
- **sub_area (podoblast)** — e.g. `Jižní věže`
- **gps** — **if available**. Site format:
  `50°34,687´N, 16°08,675´E` (Czech decimal comma; degrees + decimal
  minutes). Many rocks have no GPS → field is nullable and absence is
  not an error. Store the raw string and parsed decimal lat/lon.
- the list of **routes (cesty)** on this rock (see 4.5).

### 4.5 Routes (cesty)

Route data is read **only from the route table on the rock detail page**.
Individual route pages (`/cs/cesta/...`) are **not** fetched by the
scraper — only their URL is stored so the frontend can link out to them.

For each route row on a rock detail page, capture:

- **name** — e.g. `Avantipopolo`
- **url** — absolute, e.g. `/cs/cesta/avantipopolo-6662/`
- **difficulty** (obtížnost) — grade string, verbatim (e.g. `VI`,
  `VIIc`, `VIIb`).
- **first_ascent_date** (datum prvovýstupu) — e.g. `17.9.2007` (Czech
  `D.M.YYYY`); store both ISO-normalized and the raw string.
- **stars** (počet hvězdiček) — integer, **0, 1 or 2** (the only values
  used on the site).
- **comments_count** (počet komentářů) — integer.
- **has_photos** — boolean, from the presence of a photo icon on the row.

### 4.6 Crawler behaviour

- **Polite crawling:** configurable delay between requests, descriptive
  User-Agent, honor `robots.txt`.
- **Resumable / incremental:** re-runnable; refresh policy configurable
  (e.g. re-fetch if older than N days); progress persisted so an
  interrupted crawl can resume.
- **Idempotent upserts:** records keyed by URL; re-scraping updates, never
  duplicates.
- **Error handling:** failed fetch is logged and retried with backoff; a
  permanently failing page must not abort the whole crawl.
- **Czech text:** handle UTF-8 / diacritics; parse Czech dates and
  decimal-comma coordinates.
- **Structured logging:** counts per level (areas → sectors → rocks →
  routes).

---

## 5. Database Model

Schema lives in numbered migration files under `db/` and is applied with
a setup script (as in monkey-cup). Suggested tables:

```sql
area    ( id, name, url UNIQUE, scraped_at )

sector  ( id, area_id FK, name, url UNIQUE,
          climbing_season, climbing_restriction, scraped_at )

rock    ( id, sector_id FK, name, url UNIQUE,
          area_name, sub_area_name,
          gps_raw NULL, gps_lat NULL, gps_lon NULL, scraped_at )

route   ( id, rock_id FK, name, url UNIQUE,
          difficulty, first_ascent_date NULL, first_ascent_raw NULL,
          stars TINYINT, comments_count INT, has_photos TINYINT(1),
          scraped_at )

scrape_run ( id, started_at, finished_at NULL,
             status ENUM('planned','running','done','failed'),
             -- progress is derived from scrape_job rows, summarized here
             sectors_total, sectors_done,
             rocks_count, routes_count,
             error_message NULL )

-- The persisted plan: one row per sector to scrape (the work-list).
-- Created during planning; consumed one-by-one by the step endpoint.
scrape_job ( id, run_id FK, area_name, sector_name, sector_url,
             status ENUM('pending','running','done','failed'),
             rocks_count NULL, routes_count NULL,
             error_message NULL, started_at NULL, finished_at NULL,
             UNIQUE (run_id, sector_url) )
```

- Every entity is uniquely keyed by its piskari.cz URL (stable across
  runs → upsert target).
- GPS stored both raw and parsed (parsed columns nullable).

---

## 6. Backend API (REST / JSON)

### 6.1 Public endpoints (no auth)

- `GET /api/areas` — list areas, each with its sectors (overview feed for
  the public landing page, see 7.2).
- `GET /api/areas/{id}/sectors` — sectors of an area (with season /
  restriction).
- `GET /api/sectors/{id}/routes` — **flat list of all routes in a
  sector** (joined across rocks), each route carrying: route name,
  difficulty, stars, comments_count, has_photos, parent rock name, and
  the piskari.cz route URL. Primary feed for the sector view (see 7.3).
- `GET /api/rocks/{id}` — rock detail: name, area, sub-area, GPS, and its
  routes.
- `GET /api/search?q=...` — **search routes and rocks by name** (Czech
  diacritics-insensitive); returns matching rocks and routes with enough
  context (parent area/sector/rock) to navigate to them (see 7.4).

### 6.2 Admin endpoints (password-protected)

Single shared-secret auth (as in monkey-cup).

- `GET /api/admin/scrape/runs` — list past scrape runs with their
  summary counts and status (scraping overview, see 7.5).
- `GET /api/admin/scrape/runs/{id}` — detail + **live progress** of a
  run (current stage, processed/total, current target URL).
- `GET /api/admin/scrape/current` — the currently running scrape, if any
  (for live progress polling).
- `POST /api/admin/scrape` — **plan a new run**. In one short request,
  discover all areas and their sectors and persist the full work-list as
  `scrape_job` rows (status `pending`), with the run set to `planned`.
  Rejected if a run is already active. Returns the run id and the planned
  jobs.
- `POST /api/admin/scrape/runs/{id}/step` — **process the next pending
  job** synchronously: pick the oldest `pending` sector job, scrape it
  rock-by-rock, upsert its rocks and routes, mark the job `done` (or
  `failed` with a message), and roll its counts up into the run. Returns
  updated progress and whether more jobs remain. The frontend calls this
  repeatedly until no `pending` jobs are left, then the run is `done`.
- `GET /api/admin/scrape/runs/{id}` — run detail incl. its `scrape_job`
  rows (per-sector status) — the data the progress view renders.

Stateless, JSON in/out, prepared statements, strict input validation.

### 6.3 Execution model (resolved)

1. **Plan first.** `POST /api/admin/scrape` enumerates every sector and
   writes the work-list to `scrape_job`. The plan is persisted, so the
   set of work is fixed and visible up front.
2. **Process step-by-step, synchronously.** Each `…/step` call handles
   **at most one sector**, completing well within PHP's
   `max_execution_time` — no background workers or queue (fits shared
   hosting over SFTP).
3. **Show progress.** Progress = `sectors_done / sectors_total` plus the
   per-sector job list, all read from the DB, so it is accurate
   regardless of which client is driving the run.
4. **Resume is mandatory and robust.** Because the plan lives in the DB
   as job rows, continuing an interrupted run = keep calling `…/step`,
   which always picks the next `pending` job. This survives a closed
   browser, a server restart, or a crash mid-sector:
   - upserts are idempotent (§4.6), so re-running a job that was
     interrupted mid-sector cannot create duplicates;
   - a job left in `running` by a crash is reclaimed as `pending` on the
     next step (e.g. if not finished within a timeout);
   - the admin UI detects an unfinished run on open (`status` in
     `planned`/`running` with `pending` jobs) and offers **Continue**.

---

## 7. Frontend (React + Vite)

Built with **Vite** (React + TypeScript). The UI is **mobile-first**:
layouts, typography and touch targets are designed for phones first and
progressively enhanced for larger screens. Czech UI labels.

The app has a **public part** (browsing + search) and a
**password-protected admin part** (scraping management).

### 7.1 Navigation

Browse the hierarchy: Area → Sector → (Rock detail).

### 7.2 Public landing — areas & sectors overview

The public landing page shows an **overview of all areas and their
sectors**. Each sector shows its name and climbing season/restriction,
and links into the sector's route overview (7.3).

### 7.3 Sector overview — all routes

For a selected sector, show an **overview table of all routes** in that
sector. Columns:

- route name
- difficulty (obtížnost)
- stars (hvězdičky) — rendered as star icons
- comments count (počet komentářů)
- photos — indicator shown when `has_photos` is true
- (parent rock name, for context)

The table should support sensible sorting/filtering (at least by
difficulty and stars).

### 7.4 Rock detail

A rock can be opened to a **detail view** showing its name, area,
sub-area, GPS (when available, e.g. linkable to a map), and the list of
its routes with the same per-route attributes as 7.2.

### 7.5 Route → original piskari page

Each route row/link is **clickable and opens the original piskari.cz
route page in a new browser tab/window** (`target="_blank"`,
`rel="noopener noreferrer"`), using the stored route URL. The app does
not re-render piskari content — it links out to the source.

### 7.6 Search

A search box (available across the public part) lets the user **search
for a route or a rock by name**, calling `GET /api/search`. Results are
diacritics-insensitive and let the user jump to the rock detail (7.4) or
out to the route's piskari page (7.5).

### 7.7 Admin — scraping management

A password-protected admin part provides:

- **Scraping overview** — a list of past scrape runs with status, start/
  finish time and the resulting counts (areas / sectors / rocks /
  routes).
- **Re-scrape** — a button that plans a new run (`POST /api/admin/
  scrape`) and then drives it to completion by issuing successive
  `…/step` calls (one sector per call); disabled while a run is already
  in progress.
- **Live progress** — between `…/step` calls, show progress: overall
  `sectors_done / sectors_total`, the per-sector job list with each
  job's status, the sector currently being processed, and running rock/
  route counts (all from `GET /api/admin/scrape/runs/{id}`).
- **Continue an interrupted run** — on open, if an unfinished run exists
  (status `planned`/`running` with `pending` jobs), the UI surfaces a
  **Continue** action that resumes the `…/step` loop from the next
  pending job. Resuming a half-finished crawl is a required capability.

---

## 8. Deployment (GitHub Actions)

A single workflow `ci-build-deploy.yml` on push to `main` (mirroring
monkey-cup):

1. **Frontend:** setup Node, `npm ci`, run tests, production build.
2. **Backend:** setup PHP 8.2 (`pdo_mysql`), `composer install`, run
   PHPUnit tests against a MariaDB service container.
3. **Deploy:** install `lftp`, generate backend `.env` from CI
   vars/secrets, deploy `frontend` build and `backend/` to the server
   over SFTP.

Configuration (DB credentials, SFTP target, site base URL) comes from
GitHub environment **variables** and **secrets** — never committed.

The **scraper** runs as a separate scheduled job / manual CLI invocation
(e.g. a cron-triggered Actions workflow or on-server cron), not on every
deploy.

---

## 9. Non-Functional Requirements

- UTF-8 / Czech diacritics end-to-end.
- Prepared statements; strict validation of all input.
- Idempotent, resumable scraping; safe to re-run.
- Clear separation: scraper writes data, API reads data, frontend renders.

---

## 10. Open Questions

- Exact DOM selectors per page type to be confirmed against live HTML
  during implementation.

**Resolved decisions:**

- Scrape execution: **plan first (persisted work-list), then synchronous
  one-sector-per-request steps** driven by the admin UI, with mandatory
  robust resume of interrupted runs (§6.3) — no background workers or
  queue, fits shared hosting.
- React routing: **React Router** (§2, §7).
- Stars: values **0, 1 or 2** only (§4.5).
- Routes: rock-page table only; individual route pages are not scraped
  (§4.5).
