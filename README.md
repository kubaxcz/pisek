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
`/api/search?q=...`. Admin API under `/api/admin/scrape/*` (requires the
`X-Admin-Password` header).

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
  `CORS_ALLOW_ORIGIN`.
- Secrets: `SFTP_PASSWORD`, `DB_PASSWORD`, `ADMIN_PASSWORD`.
