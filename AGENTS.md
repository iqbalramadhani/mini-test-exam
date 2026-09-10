# Mini Test Exam — Agent Instructions

## Repo shape

- PHP 8.1+ native MVC backend plus React 18 + Vite 5 frontend; there is no framework router or model-per-table pattern.
- PHP entry point: `public/index.php`; application wiring: `application/core/application.php`; API dispatch: `application/core/api_route.php`; API controllers: `application/api/*.php`; database logic: `application/model/model.php`.
- React source is `react-app/src/`. `react-app/vite.config.js` sets the app base to `/react-app/` and proxies `/api` to `http://localhost:8000`.
- `npm run build` emits Vite output to `public/react-app/`. Edit `react-app/src/`; treat `public/react-app/` as generated build output.
- `composer.json` and `react-app/package.json` are the package manifests; `composer.lock` and `react-app/package-lock.json` are dependency sources of truth.

## Setup and verification

- Copy `.env.example` to `.env` for local DB/SMTP settings; `.env` is ignored and must never be committed.
- PHP setup: `composer install` then `php bin/migrate.php migrate`. Migrations live in `bin/migrate.php` and `migrations/`; the CLI also supports `status`, `rollback`, and `fresh`.
- Frontend setup: `cd react-app && npm ci`.
- Local development: run `cd public && php -S localhost:8000` and `cd react-app && npm run dev` in separate terminals.
- Full verification order used by CI: `composer test`, `cd react-app && npm test`, `cd react-app && npm run lint`, `cd react-app && npm run build`.
- Focused checks: `vendor/bin/phpunit tests/Unit/EnvTest.php` and `cd react-app && npm test -- src/...`.
- `composer test` currently passes 147 tests using in-memory SQLite test fixtures; no external MySQL service is required for the suite.
- `npm test` currently passes 12 tests. `npm run lint` exits successfully with three existing warnings (`AuthContext.jsx`, `TakeExam.jsx`); warnings are not CI failures.

## Runtime and workflow gotchas

- API auth is session-based. `application/core/api_route.php` initializes the session and validates CSRF before dispatching mutating requests; do not bypass that handling when testing APIs.
- `public/.htaccess` must preserve the rewritten query string with `[L,QSA]`; removing `QSA` breaks API and migration routes.
- The deployment workflow runs PHP lint, tests, frontend lint/tests, then build before rsync. Its rsync excludes `./react-app/` and `/react-app/` specifically so `public/react-app/` is retained.
- Deploy secrets are `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD`, `MIGRATION_SECRET`, and `APP_URL`; deployment migrations require `X-Migration-Secret` on `POST /api/migration/run`.
- `.gitignore` excludes `.env`, databases, `vendor/`, `node_modules/`, logs, and temporary files, but intentionally tracks `public/react-app/` build output.
- Before debugging a known bug, search `CHANGELOG_FIXES.md` by symptom or recent `### Fix #` entries. Add a changelog entry only after the fix is verified; keep the entry factual with numbers and reproduction evidence.
