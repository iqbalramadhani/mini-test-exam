# CHANGELOG — Perbaikan & Fitur MINI

## Fix #1 — Migrasi Database Otomatis + ENV Support + PHP 8.2 Deprecation Fixes
**Tanggal:** 2026-09-08
**Status:** ✅ LIVE

| Item | Detail |
|---|---|
| **File** | `bin/migrate.php`, `bin/migration-runner.php`, `migrations/*.php`, `application/libs/env.php`, `application/config/config.php`, `application/core/controller.php`, `application/model/model.php`, `.env.example`, `.gitignore` |
| **Masalah** | Aplikasi tidak punya sistem migrasi database; property dinamis di Model/Controller memicu deprecation warning di PHP 8.2+; kredensial DB hardcode di config.php |
| **Akar** | MINI v1 dirancang minimalis tanpa dependency management, tapi tidak ada cara versioning skema DB yang terstruktur |
| **Fix** | 1. Tambah `bin/migrate.php` + `MigrationRunner` dengan auto-normalizer MySQL→SQLite DDL<br>2. Tambah `application/libs/env.php` untuk load `.env` tanpa library eksternal<br>3. Deklarasi properti eksplisit di `Controller` ($db, $model) dan `Model` ($db)<br>4. Ubah `ERRMODE_WARNING` → `ERRMODE_SILENT`<br>5. Buat 2 migration example: `001_create_song_table`, `002_add_user_table` |
| **Verifikasi** | `php bin/migrate.php fresh` → berhasil buat tabel song + user; `php bin/migrate.php rollback` → berhasil hapus; `php bin/migrate.php status` → menampilkan history batch; tidak ada deprecation warning lagi |
| **Pelajaran** | SQLite tidak support `ON UPDATE CURRENT_TIMESTAMP`, `AUTO_INCREMENT` di kolom non-PK, atau `ENUM` — normalizer di `MigrationRunner::normalizeSql()` menangani ini secara transparan agar file migrasi tetap MySQL-first |
| **Log Keyword** | `migrate`, `rollback`, `normalizeSql`, `fileToClassName`, `loadDotEnv`, `ERRMODE_SILENT` |
| **Deploy** | Tidak perlu deploy — fitur ini berjalan lokal via CLI |
