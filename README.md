# Mini Test Exam

Aplikasi ujian/tes online sederhana dengan **PHP backend** (API berbasis MVC) dan **React frontend** (Vite + Tailwind CSS).

## Fitur

- **Autentikasi** — Register, Login, Logout dengan session-based auth
- **Manajemen Ujian** — CRUD ujian (buat, lihat, edit, hapus)
- **Manajemen Soal** — Tambah, edit, hapus soal pilihan ganda per ujian
- **Pilihan Jawaban** — Setiap soal mendukung multiple choice (A–F) dengan penanda jawaban benar
- **Dashboard** — Halaman utama untuk melihat daftar ujian
- **Exam Builder** — Antarmuka untuk menyusun soal dalam ujian
- **Protected Routes** — Halaman tertentu hanya bisa diakses setelah login

## Tech Stack

| Layer       | Teknologi                          |
|-------------|------------------------------------|
| Backend     | PHP 8.x (native MVC, PDO)         |
| Frontend    | React 18 + React Router v6        |
| Build Tool  | Vite 5                            |
| Styling     | Tailwind CSS 4                    |
| Database    | MySQL / SQLite                    |

## Struktur Proyek

```
mini-test-exam/
├── application/
│   ├── api/            # API controllers (auth, exams)
│   ├── config/         # Konfigurasi database & aplikasi
│   ├── controller/     # MVC controllers (home, songs, problem)
│   ├── core/           # Application router, base controller, API routing
│   ├── libs/           # Helper, security, env loader
│   ├── model/          # Model database (PDO)
│   └── view/           # PHP view templates
├── migrations/         # Database migration files
├── public/             # Document root (entry point)
│   ├── index.php       # Main entry point
│   ├── router.php      # PHP built-in server router
│   ├── css/            # Stylesheet
│   ├── js/             # JavaScript
│   └── react-app/      # Built React app (hasil build)
├── react-app/          # React source code
│   ├── src/
│   │   ├── pages/      # Login, Register, Dashboard, ExamBuilder
│   │   ├── components/ # Reusable components (Navbar, dll)
│   │   ├── context/    # AuthContext (state management)
│   │   └── api.js      # API client
│   ├── package.json
│   └── vite.config.js
├── bin/                # CLI tools (migrate.php)
├── .env.example        # Template environment variables
└── composer.json
```

## Instalasi

### 1. Clone & Setup Environment

```bash
git clone <repo-url> mini-test-exam
cd mini-test-exam
cp .env.example .env
```

Edit `.env` sesuai konfigurasi database kamu:

```env
DB_TYPE=mysql       # atau sqlite
DB_HOST=127.0.0.1
DB_NAME=mini
DB_USER=root
DB_PASS=
DB_CHARSET=utf8mb4
```

### 2. Jalankan Migrasi Database

```bash
php bin/migrate.php migrate
```

Perintah migrasi lainnya:

```bash
php bin/migrate.php status        # Lihat status migrasi
php bin/migrate.php rollback      # Rollback batch terakhir
php bin/migrate.php fresh         # Drop semua tabel & jalankan ulang semua migrasi
```

### 3. Install Dependencies Frontend

```bash
cd react-app
npm install
```

### 4. Jalankan Development Server

**Terminal 1 — PHP Backend:**

```bash
cd public
php -S localhost:8000
```

**Terminal 2 — React Frontend (dev mode):**

```bash
cd react-app
npm run dev
```

Akses aplikasi di `http://localhost:5173/react-app/`

### 5. Build untuk Production

```bash
cd react-app
npm run build
```

Hasil build akan otomatis di-copy ke `public/react-app/`. Setelah itu cukup jalankan PHP server saja:

```bash
cd public
php -S localhost:8000 router.php
```

## API Endpoints

### Auth

| Method | Endpoint             | Deskripsi              |
|--------|----------------------|------------------------|
| POST   | `/api/auth/register` | Registrasi user baru   |
| POST   | `/api/auth/login`    | Login                  |
| GET    | `/api/auth/me`       | Info user yang login   |
| POST   | `/api/auth/logout`   | Logout                 |

### Ujian (Exams)

| Method | Endpoint                               | Deskripsi                    |
|--------|----------------------------------------|------------------------------|
| GET    | `/api/test`                            | Daftar semua ujian           |
| GET    | `/api/test/{id}`                       | Detail ujian + soal          |
| POST   | `/api/test`                            | Buat ujian baru              |
| PUT    | `/api/test/{id}`                       | Update ujian                 |
| DELETE | `/api/test/{id}`                       | Hapus ujian                  |
| GET    | `/api/test/{id}/questions`             | Daftar soal dalam ujian      |
| POST   | `/api/test/{id}/questions`             | Tambah soal baru             |
| PUT    | `/api/test/{id}/questions/{questionId}`| Update soal                  |
| DELETE | `/api/test/{id}/questions/{questionId}`| Hapus soal                   |

> Semua endpoint ujian membutuhkan autentikasi (session).

## Database Migrations

File migrasi ada di folder `migrations/`:

| File                           | Deskripsi                               |
|--------------------------------|-----------------------------------------|
| `001_create_song_table.php`    | Tabel song (demo bawaan)                |
| `002_add_user_table.php`       | Tabel user (auth)                       |
| `003_create_exam_tables.php`   | Tabel exam, question, choice            |

Migrasi mendukung MySQL dan SQLite — DDL MySQL otomatis di-translate ke SQLite saat menggunakan driver SQLite.

## Lisensi

MIT License
