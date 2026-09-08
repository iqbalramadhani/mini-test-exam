# CLAUDE.md — MINI PHP Application

## What This Is

MINI is a barebone PHP MVC skeleton (v1) by Panique. It has **no framework dependencies**, no autoloader beyond a fallback for Composer, no test suite, and no build tooling. It's designed for quick prototypes where you want raw PHP without learning a framework.

## Run Locally

```bash
# Point your web server's document root at the /public folder
# Then ensure mod_rewrite is enabled and visit:
http://localhost/public/
```

Database setup (see [_install/](_install/)):
1. `01-create-database.sql` — creates the `mini` database
2. `02-create-table-song.sql` — creates the `song` table
3. `03-insert-demo-data-into-table-song.sql` — inserts sample data

Edit [application/config/config.php](application/config/config.php) to set your DB credentials (`DB_USER`, `DB_PASS`).

## Database Migrations

A simple CLI migration system lives in `bin/migrate.php` and `migrations/`. It works with both MySQL and SQLite (auto-translates MySQL DDL).

```bash
php bin/migrate.php migrate       # Run pending migrations
php bin/migrate.php rollback      # Rollback last batch
php bin/migrate.php status        # Show migration history
php bin/migrate.php fresh         # Drop all tables and re-run all migrations
```

Migration files go in `migrations/` — naming: `{number}_{slug}.php`. Each class must have `up($db)` and `down($db)` methods. The `MigrationRunner::normalizeSql()` method transparently converts MySQL DDL (AUTO_INCREMENT, ENUM, ENGINE, ON UPDATE) to SQLite-compatible SQL when the DB driver is SQLite.

## Architecture

This is a hand-rolled MVC with **no routing framework** — routing logic lives entirely in two files:

| Layer | Path | Purpose |
|---|---|---|
| Entry point | [public/index.php](public/index.php) | Defines `ROOT`, `APP`, loads core + config, boots `Application` |
| Router | [application/core/application.php](application/core/application.php) | Parses `$_GET['url']`, instantiates controllers, dispatches methods |
| Base controller | [application/core/controller.php](application/core/controller.php) | Opens PDO connection, loads `Model` — all controllers extend this |
| Model | [application/model/model.php](application/model/model.php) | Single monolithic model with all DB operations (PDO, parameterized) |
| Helpers | [application/libs/helper.php](application/libs/helper.php) | `Helper::debugPDO()` — emulates bound SQL for debugging |
| Views | [application/view/](application/view/) | Plain PHP templates, included via `require APP . 'view/...'` |

### URL → Controller Routing

URL segments map directly to `controller/file` → `method`:

- `example.com/home` → `Home::index()`
- `example.com/songs` → `Songs::index()`
- `example.com/songs/editsong/17` → `Songs::editSong(17)`
- Missing controller or method → redirects to `problem` (404 page)
- Default (no URL) → `Home::index()`

### Key Conventions

- **All controllers extend `Controller`** — they get `$this->db` (PDO) and `$this->model` (Model) injected automatically in the constructor.
- **Views are included with `require`**, not rendered through a template engine. Data flows via plain PHP variables.
- **The single `Model` class** holds every DB method. There is no model-per-table pattern.
- **POST actions redirect** after handling (PRG pattern) — e.g., `addSong()` POSTs then `header('location: ...')` back to index.
- **Class name warning**: never name a method the same as its class (e.g., a class `Foo` with method `foo()`) — PHP treats it as a constructor, which breaks dispatch.
- **Output escaping** is done in views with `htmlspecialchars()`, not in the model — keep raw input in the DB.

### Routing Edge Case

The `.htaccess` in [public/](public/.htaccess) catches all non-file/non-directory requests and rewrites them to `index.php?url=<path>`. The root [.htaccess](.htaccess) is a fallback that forwards everything into `/public/` — if your vhost is pointed at `/public` directly, this file is unused.

## Security Notes

- Uses PDO with parameterized queries throughout — no SQL injection vector from normal usage.
- `$_GET['url']` is sanitized with `FILTER_SANITIZE_URL` before splitting.
- Output in views uses `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
- The root `.htaccess` blocks direct access to `application/` when vhost points to `public/`.
