# Mini Test Exam — Agent Instructions

## Repo shape
- Native PHP 8.1+ MVC backend plus React 18 + React Router v6 + Vite 5 frontend.
- PHP entry point: `public/index.php`; MVC routing: `application/core/application.php`; API routing: `application/core/api_route.php`; API controllers: `application/api/*.php`; database logic: `application/model/model.php`.
- React source is `react-app/src/`. `react-app/vite.config.js` sets the app base to `/react-app/` and proxies `/api` to `http://localhost:8000`.
- `npm run build` writes to `public/react-app/`; edit `react-app/src/` and rebuild instead of editing generated output.
- `composer.json` declares PHP dependencies; `react-app/package.json` declares frontend dependencies. `composer.lock` is ignored, while `react-app/package-lock.json` is tracked.

## Setup
- Copy `.env.example` to `.env` for local DB/SMTP settings; `.env` is ignored and must never be committed.
- PHP setup: `composer install` then `php bin/migrate.php migrate`. The migration CLI also supports `status`, `rollback`, and `fresh`.
- Frontend setup: `cd react-app && npm ci`.
- Local development: run `cd public && php -S localhost:8000` and `cd react-app && npm run dev` in separate terminals.
- For the built app, use `cd public && php -S localhost:8000 router.php`.

## Verification
- CI order: PHP lint, `composer test`, `cd react-app && npm ci`, `cd react-app && npm run lint`, `cd react-app && npm test`, `cd react-app && npm run build`.
- Focused checks: `vendor/bin/phpunit tests/Unit/EnvTest.php`; `cd react-app && npm test -- src/...`.
- PHP tests use in-memory SQLite fixtures; no external MySQL service is required for `composer test`.

## Runtime and workflow gotchas
- API auth is session-based. `application/core/api_route.php` initializes the session and validates CSRF before dispatching mutating requests; do not bypass that handling when testing APIs.
- Keep `public/.htaccess` query-string rewrite `[QSA,L]`; removing `QSA` breaks API and migration routes.
- Deployment builds `public/react-app/`; rsync excludes source `./react-app/` and `/react-app/` so the generated build is retained.
- Deploy secrets: `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD`, `MIGRATION_SECRET`, `APP_URL`; deployment migrations require `X-Migration-Secret` on `POST /api/migration/run`.
- `.gitignore` excludes `.env`, databases, `vendor/`, `node_modules/`, logs, and temporary files, but intentionally tracks `public/react-app/`.
- Before debugging known bugs, search `CHANGELOG_FIXES.md` by symptom or recent `### Fix #`; add an entry only after verification.
