# CHANGELOG — Perbaikan & Fitur MINI

## Fix #3 — CSRF Check Dipindah ke Base Controller
**Tanggal:** 2026-09-08
**Status:** ✅ LIVE

| Item | Detail |
|---|---|
| **File** | `application/core/controller.php`, `application/controller/songs.php` |
| **Masalah** | Validasi CSRF dilakukan di setiap action controller secara manual (duplikat kode) |
| **Akar** | Tiap controller baru perlu mengingat validasi CSRF satu per satu |
| **Fix** | Pindahkan ke `Controller::checkCsrf()` — method `protected` otomatis available di semua controller |
| **Verifikasi** | `addSong` dan `updateSong` cukup panggil `$this->checkCsrf()` satu baris |
| **Pelajaran** | Pola security check di base class = DRY + no-leak |
| **Log Keyword** | `checkCsrf`, `protected`, `CSRF` |
| **Deploy** | Tidak perlu deploy |

## Fix #2 — Security Hardening: CSRF, XSS, Path Traversal
**Tanggal:** 2026-09-08
**Status:** ✅ LIVE

| Item | Detail |
|---|---|
| **File** | `application/libs/security.php`, `application/core/application.php`, `application/controller/songs.php`, `application/view/songs/index.php`, `application/view/songs/edit.php`, `public/index.php` |
| **Masalah** | 1. Path traversal via URL controller parameter<br>2. CSRF void pada form POST (add/update song)<br>3. Tidak ada security headers<br>4. Input ID tidak divalidasi<br>5. Error reporting expose DB credentials di dev |
| **Akar** | MINI v1 tidak dirancang untuk production security — semua validasi manual di setiap layer |
| **Fix** | 1. Buat `Security` helper class: CSRF token generate/validate, controller name whitelist, ID validation<br>2. Router sekarang validasi controller name via regex sebelum load file<br>3. Form POST wajib CSRF token + server-side validation<br>4. Security headers: X-Frame-Options, X-Content-Type-Options, CSP, HSTS<br>5. Trim + validate input sebelum proses DB |
| **Verifikasi** | `curl -d 'csrf_token=wrong' ...` → 404 redirect; invalid controller → fallback to problem; valid requests tetap work |
| **Pelajaran** | MINIMALIST framework != insecure — perlu defensive coding di setiap user input point |
| **Log Keyword** | `CSRF`, `path_traversal`, `Security::`, `htmlspecialchars`, `filter_var` |
| **Deploy** | Tidak perlu deploy — fitur ini berjalan otomatis |

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
