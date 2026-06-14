# Piskari — pískovcové lezení

A catalogue browser built on top of the public sandstone-climbing guidebook
[piskari.cz](https://www.piskari.cz). It scrapes areas, sectors, rocks/towers
and routes into a database and presents them in a mobile-first web app.

See [specification.md](specification.md) for the full spec.

## Stack

- **Backend** — plain PHP 8.2 REST API (`Piskari\` namespace), PDO + MariaDB.
- **Frontend** — React + TypeScript (Vite), React Router. Mobile-first.
- **Scraper** — PHP CLI / admin-triggered, sharing the backend data layer.
- **CI/CD** — GitHub Actions: build + test, then SFTP deploy.

## Layout

```
backend/    PHP API + scraper (src/, public/index.php, bin/scrape.php)
db/         MariaDB migrations (001_catalog.sql, 002_scrape.sql) + scripts
frontend/   Vite React app
.github/    CI workflow
```

## Local development

### 1. Database

```bash
cd db
./start-mariadb.sh        # local MariaDB in Docker (port 3306)
DB_NAME=piskari ./setup-db.sh   # apply migrations
```

### 2. Backend

```bash
cd backend
cp .env.example .env      # set DB_* and ADMIN_PASSWORD
composer install
./run-backend.sh          # http://127.0.0.1:8080
```

Public API: `/api/areas`, `/api/sectors/{id}/routes`, `/api/rocks/{id}`,
`/api/search?q=...`, `/api/config`. Auth: `/api/auth/google`, `/api/auth/me`,
`/api/auth/logout`. Per-user route entries: `GET /api/routes/{id}/ascents`,
`PUT|DELETE /api/routes/{id}/ascent`. Admin API under `/api/admin/scrape/*`
(requires a logged-in user whose e-mail is in `ADMIN_EMAILS`).

### Accounts, ratings & admin

- **Sign-in** uses Google Identity Services. The frontend obtains a Google ID
  token; the backend verifies it, creates/updates the user, and issues an
  opaque session token (sent as `Authorization: Bearer …`).
- A signed-in user can open any route and record: **route stars (1–5)**,
  **belay stars (1–5)**, the **ordered sequence of protection placed**
  (kruh, uzel, hodiny, hrot, strom, jiné) and a **note**. One editable entry
  per user per route. The route overview shows aggregate user-stars and the
  number of belay records; the route dialog shows everyone's entries.
- **Admin** (scraping) is granted to signed-in users whose e-mail is listed in
  the `ADMIN_EMAILS` env whitelist.

### Google Cloud setup

Create an **OAuth 2.0 Client ID** (type *Web application*) and add your site to
**Authorised JavaScript origins** (e.g. `http://localhost:5173` for dev and
`https://pisek.hkchocen.cz` for prod). Put the client ID in `GOOGLE_CLIENT_ID`.
No client secret is needed (the ID-token flow is used).

### 3. Frontend

```bash
cd frontend
npm install
npm run dev               # http://127.0.0.1:5173 (proxies /api to :8080)
```

## Scraping

The scrape is **planned first** (the full per-sector work-list is persisted),
then processed **one sector per request** synchronously, so it fits shared
hosting with no background workers. An interrupted run is resumable — the plan
lives in the DB and processing always continues from the next pending job.

- **From the UI:** open `/admin`, enter the admin password, click *Nový
  scraping*. The app drives the run to completion and shows live progress;
  *Pokračovat* resumes an interrupted run.
- **From the CLI:**

  ```bash
  cd backend
  php bin/scrape.php        # plans a new run or resumes the active one
  ```

## Tests & lint

```bash
cd backend && ./run-tests.sh && ./run-lint.sh
cd frontend && npm test && npm run lint
```

## Deployment

Push to `main` triggers `.github/workflows/ci-build-deploy.yml`: it lints,
tests and builds both apps, then (when SFTP secrets are configured) deploys
`frontend/dist` and `backend/` over SFTP. Configure these in the GitHub `prod`
environment:

- Variables: `SFTP_HOST`, `SFTP_PORT`, `SFTP_USERNAME`, `SFTP_REMOTE_FRONTEND`,
  `SFTP_REMOTE_BACKEND`, `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`,
  `GOOGLE_CLIENT_ID`, `ADMIN_EMAILS`, `CORS_ALLOW_ORIGIN`.
- Secrets: `SFTP_PASSWORD`, `DB_PASSWORD`.
