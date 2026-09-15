# CLAUDE.md — Mini Test Exam

## What This Is

An online exam application with a PHP MVC backend and a React/Vite/Tailwind frontend. Users can create exams, build questions, take exams in tryout (timed) or practice (untimed with instant feedback) modes, and view results. Supports both authenticated users and guests.

```
┌─────────────────────────────────┐
│  React 18 + Vite 5 + Tailwind   │  ← Frontend (react-app/)
│  AuthContext · SweetAlert2      │
│  react-markdown + KaTeX         │
└──────────────┬──────────────────┘
               │ /api/* proxy
┌──────────────▼──────────────────┐
│  PHP 8.1+ Hand-rolled MVC       │  ← Backend (application/)
│  PDO · Session auth · CSRF       │
│  MySQL / SQLite                  │
└─────────────────────────────────┘
```

## Run Locally

```bash
# 1. Set up environment
cp .env.example .env
# Edit .env: set DB credentials and SMTP settings

# 2. Install PHP dependencies and run migrations
composer install
php bin/migrate.php migrate

# 3. Install frontend dependencies and start dev servers
cd react-app && npm install && cd ..
bin/dev.sh
```

Then visit `http://localhost:8000` (PHP backend) and `http://localhost:5173` (Vite frontend, auto-proxies `/api` to PHP).

Database setup: migrations run automatically via `php bin/migrate.php migrate`. Uses SQLite by default (`mini.db`) or MySQL if configured in `.env`.

## Architecture

### Backend (PHP) — Two Routers

| Layer | Path | Purpose |
|---|---|---|
| Entry point | [public/index.php](public/index.php) | Defines `ROOT`, `APP`, loads core + config, boots `Application` |
| Web router | [application/core/application.php](application/core/application.php) | Parses `$_GET['url']` → web controllers (Home, Songs, Problem) |
| API router | [application/core/api_route.php](application/core/api_route.php) | Parses `/api/*` → JSON controllers with CSRF validation |
| Auth controller | [application/api/auth.php](application/api/auth.php) | Register, login, verify email, profile, password change |
| Exam controller | [application/api/exams.php](application/api/exams.php) | Exam CRUD, question CRUD, bulk question import |
| Attempt controller | [application/api/attempt.php](application/api/attempt.php) | Start exam, submit answers, get results |
| Migration controller | [application/api/migration.php](application/api/migration.php) | CLI + API migration runner |
| Security | [application/libs/security.php](application/libs/security.php) | CSRF, rate limiting, password validation, session hardening, RBAC |
| Mailer | [application/libs/mailer.php](application/libs/mailer.php) | Email sending via PHPMailer |
| Base controller | [application/core/controller.php](application/core/controller.php) | PDO connection, model loading |
| Model | [application/model/model.php](application/model/model.php) | Legacy monolithic model (for web routes) |
| Views | [application/view/](application/view/) | Plain PHP templates |

### Frontend (React)

| Path | Purpose |
|---|---|
| [react-app/src/App.jsx](react-app/src/App.jsx) | Routes + `AuthProvider` wrapper |
| [react-app/src/context/AuthContext.jsx](react-app/src/context/AuthContext.jsx) | Auth state (user, login, register, logout) + `ProtectedRoute` |
| [react-app/src/pages/Dashboard.jsx](react-app/src/pages/Dashboard.jsx) | Exam list with search, pagination, create/edit/delete, publish toggle |
| [react-app/src/pages/ExamBuilder.jsx](react-app/src/pages/ExamBuilder.jsx) | Per-question exam builder — add/edit/delete questions with choices, explanations, weights. Bulk import supported. |
| [react-app/src/pages/AvailableExams.jsx](react-app/src/pages/AvailableExams.jsx) | Public exam listing; mode selector dialog (Tryout / Practice with limit & randomize options) |
| [react-app/src/pages/TakeExam.jsx](react-app/src/pages/TakeExam.jsx) | Exam interface — timer, question navigation, submit; practice mode shows instant feedback |
| [react-app/src/pages/ExamResult.jsx](react-app/src/pages/ExamResult.jsx) | Post-exam results: score, correct/wrong counts, duration, per-question review |
| [react-app/src/pages/Profile.jsx](react-app/src/pages/Profile.jsx) | User profile: change name, change password |
| [react-app/src/pages/Admin.jsx](react-app/src/pages/Admin.jsx) | Admin panel (stub) |
| [react-app/src/pages/AdminLogin.jsx](react-app/src/pages/AdminLogin.jsx) | Admin login page |
| [react-app/src/pages/Welcome.jsx](react-app/src/pages/Welcome.jsx) | Landing page — guest name entry, feature cards |
| [react-app/src/pages/VerifyEmail.jsx](react-app/src/pages/VerifyEmail.jsx) | Email verification via token from URL |
| [react-app/src/components/Navbar.jsx](react-app/src/components/Navbar.jsx) | Navigation bar — auth-aware links, shows guest name or logged-in user |
| [react-app/src/components/FormattedText.jsx](react-app/src/components/FormattedText.jsx) | Markdown + KaTeX renderer for question text and explanations |
| [react-app/src/api.js](react-app/src/api.js) | API client — `authApi`, `examApi`, `attemptApi` |

### Routes

| Path | Component | Protected |
|---|---|---|
| `/` | Welcome | No |
| `/verify-email` | VerifyEmail | No |
| `/dashboard` | Dashboard | Yes |
| `/exam/:id/build` | ExamBuilder | Yes |
| `/exams` | AvailableExams | No |
| `/take/:id` | TakeExam | No |
| `/result/:attemptId` | ExamResult | No |
| `/profile` | Profile | Yes |
| `/admin` | Admin (redirects non-admin to `/admin-login`) | No |
| `/admin-login` | AdminLogin | No |

Navbar is hidden on `/take/*` routes.

## Backend API Endpoints

### Auth (session-based, no CSRF token needed — Origin/Referer validated)

| Method | Endpoint | Purpose |
|---|---|---|
| POST | `/api/auth/register` | Register (requires email verification) |
| GET | `/api/auth/verify?token=` | Verify email |
| POST | `/api/auth/login` | Login with email/username + password |
| GET | `/api/auth/me` | Current user info |
| POST | `/api/auth/logout` | Logout |
| POST | `/api/auth/update-profile` | Update display name |
| POST | `/api/auth/change-password` | Change password |

### Exams (requires auth; ownership verification on writes)

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/api/exams` | List exams (supports `?search=&page=&limit=`) |
| GET | `/api/exams/:id` | Get exam + questions + choices |
| POST | `/api/exams` | Create exam |
| PUT | `/api/exams/:id` | Update exam metadata |
| DELETE | `/api/exams/:id` | Delete exam |
| GET | `/api/exams/:id/questions` | List questions |
| POST | `/api/exams/:id/questions` | Add single question |
| POST | `/api/exams/:id/questions/bulk` | Bulk add questions |
| PUT | `/api/exams/:id/questions/:qid` | Update question |
| DELETE | `/api/exams/:id/questions/:qid` | Delete question |

### Attempts (open — guests and authenticated users)

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/api/attempts/published` | List published exams with stats |
| POST | `/api/attempts/start/:examId` | Start attempt (`{mode, limit?, randomize?}`) |
| POST | `/api/attempts/:attemptId/submit` | Submit answers (`{answers: [{question_id, selected_choice_index}]}`) |
| GET | `/api/attempts/:attemptId` | Get attempt results |

### Migration (protected by `X-Migration-Secret` header)

| Method | Endpoint | Purpose |
|---|---|---|
| POST | `/api/migration/run` | Run pending migrations |

## Database Migrations

All migrations are in `migrations/`. Naming: `{number}_{slug}.php`. Each class has `up($db)` and `down($db)`.

| # | File | Purpose |
|---|---|---|
| 1 | `001_create_song_table.php` | Demo song table |
| 2 | `002_add_user_table.php` | User table (id, username, email, password_hash, role) |
| 3 | `003_create_exam_tables.php` | exam, question, choice tables |
| 4 | `004_add_question_fields.php` | explanation, question_type columns |
| 5 | `005_add_keterangan_field.php` | keterangan column on question |
| 6 | `006_create_attempt_tables.php` | attempt, user_answer tables |
| 7 | `007_add_name_to_user.php` | name column on user |
| 8 | `008_add_weight_to_question.php` | weight column on question |
| 9 | `009_add_score_to_choice.php` | score column on choice (TKP scoring) |
| 10 | `010_add_email_confirmation.php` | confirmation_token, token_expires_at on user |
| 11 | `011_add_mode_to_attempt.php` | mode column on attempt (tryout/practice) |
| 12 | `012_make_attempt_user_id_nullable.php` | Allow guest attempts |
| 13 | `013_add_is_randomized_to_exam.php` | is_randomized column on exam |

CLI usage:
```bash
php bin/migrate.php migrate       # Run pending migrations
php bin/migrate.php rollback      # Rollback last batch
php bin/migrate.php status        # Show migration history
php bin/migrate.php fresh         # Drop all and re-run
```

## Key Conventions

- **API controllers** each have `getDbConnection()` and `getJsonInput()` as protected methods — overridable in test subclasses to inject fake input.
- **Exam ownership** is verified via `verifyExamOwnership($examId)` before any write operation. Admins bypass this check.
- **Question types**: `choice` (single correct), `multiple` (multiple correct), `essay`.
- **Choice scoring**: each choice can have a `score` value. If max score > 0 per question, the exam uses TKP-style partial scoring; otherwise uses binary correct/incorrect with `weight`.
- **Practice mode** (`mode=practice`): no timer, shows correct answer + explanation immediately after selection. Supports `limit` (max questions) and `randomize` (shuffle order).
- **Tryout mode** (`mode=tryout`): timer enforced, correct answers hidden until submission. Auto-submits when time expires.
- **Rate limiting**: file-based, 5 failed login attempts per 15 minutes per IP → temporary block.
- **Password strength**: min 8 chars, must include uppercase, lowercase, and digit.
- **Output escaping**: `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` in views.
- **Class name warning**: never name a method the same as its class — PHP treats it as a constructor.

## Security Notes

- PDO parameterized queries throughout — no SQL injection from normal usage.
- CSRF: Origin/Referer header validation in [api_route.php](application/core/api_route.php) for all mutating requests (POST/PUT/DELETE).
- Session hardening: httponly, samesite=Lax, secure cookie when HTTPS.
- Session regeneration on login to prevent fixation attacks.
- File-based rate limiting for login attempts (`tmp/rate_limit/`).
- Root `.htaccess` blocks direct access to `application/` when vhost points to `public/`.
- API migration endpoint requires `X-Migration-Secret` header.

## Testing

```bash
# PHP unit + integration tests
composer test

# Frontend tests (Vitest + React Testing Library + MSW)
cd react-app && npm test
```

Testable controllers override `getDbConnection()` and `getJsonInput()` to inject fake data. See `tests/Unit/` for examples.

Frontend uses MSW for API mocking in `react-app/src/__tests__/`.

## CI/CD

GitHub Actions workflow at [`.github/workflows/deploy.yml`](.github/workflows/deploy.yml):

1. PHP syntax lint → `composer test`
2. Frontend `npm run lint` → `npm test` → `npm run build`
3. Bundle via rsync (excludes `react-app/`, `tests/`, `.git`, `node_modules`, `*.db`)
4. Deploy via FTP
5. Trigger migrations: `POST /api/migration/run` with `X-Migration-Secret`

## Build Output

`npm run build` in `react-app/` produces a production bundle copied to `public/react-app/`. The build script also copies `favicon.svg` and `icons.svg` to `public/`.
