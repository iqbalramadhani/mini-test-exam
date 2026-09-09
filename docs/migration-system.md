# Migration System — MINI PHP Framework

## Overview

A lightweight CLI-based migration system that auto-translates MySQL DDL to SQLite-compatible SQL. Works with both drivers without changing migration files.

## Commands

```bash
php bin/migrate.php migrate       # Run pending migrations
php bin/migrate.php rollback      # Rollback last batch
php bin/migrate.php status        # Show history & pending
php bin/migrate.php fresh         # Drop all tables, re-run all migrations
```

## Adding a New Migration

1. Create file: `migrations/{number}_{description}.php`
   ```bash
   touch migrations/003_add_table_name.php
   ```
2. Write the class with `up($db)` and `down($db)` methods using **MySQL syntax** — the normalizer handles SQLite conversion:
   ```php
   class AddTableName
   {
       public function up($db): void
       {
           $sql = "CREATE TABLE IF NOT EXISTS table_name (
               id INT AUTO_INCREMENT PRIMARY KEY,
               name VARCHAR(255) NOT NULL,
               created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
           ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

           $runner = new MigrationRunner($db);
           $db->exec($runner->normalizeSql($sql));
       }

       public function down($db): void
       {
           $db->exec("DROP TABLE IF EXISTS table_name");
       }
   }
   ```
3. Run: `php bin/migrate.php migrate`

## SQLite Normalization Rules

| MySQL Syntax | SQLite Translation |
|---|---|
| `AUTO_INCREMENT` | Removed (auto-increment handled by `INTEGER PRIMARY KEY`) |
| `ON UPDATE CURRENT_TIMESTAMP` | Removed |
| `ENGINE=InnoDB` | Removed |
| `DEFAULT CHARSET=utf8mb4` | Removed |
| `ENUM('a','b')` | `VARCHAR(50)` |
| `TINYINT(1)` | `INTEGER` |
| `INSERT IGNORE` | `INSERT OR IGNORE` |
| Inline `INDEX idx(col)` | Stripped from CREATE TABLE |

## Database Configuration

Set database type in `.env`:
- `DB_TYPE=mysql` + fill `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`
- `DB_TYPE=sqlite` + `DB_NAME=mini` (creates `mini.db` in project root)

The migration runner auto-detects the driver via `PDO::ATTR_DRIVER_NAME`.

## Production Deployment

The GitHub Actions deploy job **uploads files via FTP only** — it cannot run migrations because the MySQL server is on `localhost` (internal to the hosting provider) and not reachable from GitHub's CI runners.

**To run migrations after deploy, use one of these methods:**

### Method 1: Via SSH (preferred)
```bash
ssh user@your-server 'cd /path/to/public && php bin/migrate.php migrate'
```

### Method 2: Via cPanel Terminal
```bash
cd /home/balewebi/domains/soal.bale.web.id/public_html
php bin/migrate.php migrate
```

### Method 3: Via cPanel Cron Job
Set up a daily cron to keep tables in sync:
```
0 2 * * * /usr/bin/php /home/balewebi/domains/soal.bale.web.id/public_html/bin/migrate.php migrate
```
