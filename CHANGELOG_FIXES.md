# CHANGELOG — Perbaikan & Fitur MINI

## Fix #11 — Import Soal Pakai Satu Endpoint Bulk (Bukan Loop Per-Soal)
**Tanggal:** 2026-09-09
**Status:** ✅ LIVE

| Item | Detail |
|---|---|
| **File** | `application/api/exams.php`, `application/core/api_route.php`, `react-app/src/api.js`, `react-app/src/pages/ExamBuilder.jsx` |
| **Masalah** | `handleImport()` memanggil `examApi.addQuestion()` berulang kali (loop N soal = N request HTTP) |
| **Akar** | Tidak ada endpoint bulk insert soal — hanya ada `POST /api/exams/:id/questions` untuk satu soal |
| **Fix** | 1. Tambah method `ExamController::storeQuestionsBulk(int $examId)` di `exams.php`: menerima `{questions: [...]}` dalam satu body, insert semua soal + choices dalam satu transaction.<br>2. Daftarkan route `POST /api/exams/:id/questions/bulk` di `api_route.php`.<br>3. Tambah `addQuestionsBulk` di `api.js`.<br>4. Ubah `handleImport()` di `ExamBuilder.jsx` — kirim semua soal sekaligus dalam satu request, mapping `res.question_ids` kembali ke state. |
| **Verifikasi** | Import 3 soal → satu request POST ke `/exams/:id/questions/bulk`, semua soal tersimpan, ID dikembalikan dan di-map ke state |
| **Pelajaran** | Batch operations harus pakai single transaction — lebih cepat, atomic, dan satu request daripada N request sequential |
| **Log Keyword** | `storeQuestionsBulk`, `bulk`, `transaction`, `addQuestionsBulk` |
| **Deploy** | `cd react-app && npm run build` → copy `dist/` ke `public/react-app/` |

## Fix #10 — Tombol "Mulai Ujian" Dinonaktifkan Bila Ujian Belum Punya Soal
**Tanggal:** 2026-09-09
**Status:** ✅ LIVE

| Item | Detail |
|---|---|
| **File** | `react-app/src/pages/AvailableExams.jsx` |
| **Masalah** | Ujian yang sudah dipublikasi tapi belum punya soal tetap menampilkan tombol "Mulai Ujian" yang bisa diklik — user masuk lalu API POST `/attempts/start/:id` menolak dengan error "Ujian ini belum memiliki soal" |
| **Akar** | Tidak ada guard di UI; `question_count` sudah tersedia dari endpoint `GET /api/attempts/published` (LEFT JOIN question) tapi tidak dipakai untuk disable tombol |
| **Fix** | Kondisi `exam.question_count > 0`: render `<Link>` "Mulai Ujian" seperti biasa; selain itu render `<span>` non-klik abu-abu bertuliskan "Belum ada soal" |
| **Verifikasi** | Ujian dengan 0 soal → tombol diganti label "Belum ada soal", tidak bisa diklik; ujian dengan soal → tombol "Mulai Ujian" normal |
| **Pelajaran** | Validasi server (400 "belum memiliki soal") sebaiknya selalu dipantulkan ke UI sebagai state disabled — cegah user masuk ke flow yang pasti gagal |
| **Log Keyword** | `question_count`, `AvailableExams`, `Mulai Ujian`, `disabled` |
| **Deploy** | `cd react-app && npm run build` → copy `dist/` ke `public/react-app/` |

## Fix #9 — Import Soal Langsung Simpan ke Database
**Tanggal:** 2026-09-09
**Status:** ✅ LIVE

| Item | Detail |
|---|---|
| **File** | `react-app/src/pages/ExamBuilder.jsx` |
| **Masalah** | Tombol "Import" di modal hanya menambahkan soal ke state lokal (`questions`), tidak menyimpan ke database. User harus klik "Simpan" per soal satu per satu agar soal masuk DB — jika lupa, saat ujian dibuka muncul error "Ujian ini belum memiliki soal" |
| **Akar** | `handleImport()` sebelumnya hanya melakukan `setQuestions([...])` tanpa memanggil API `addQuestion`. Pola ini konsisten dengan workflow manual ("Tambah Soal" → isi → simpan per soal), tapi kurang intuitif untuk import massal |
| **Fix** | Ubah `handleImport` menjadi async: setiap soal di-import via `examApi.addQuestion(id, payload)` secara parallel menggunakan `Promise.all`, lalu state diupdate dengan `id` yang dikembalikan API. Tambah loading state + toast notifikasi jumlah soal berhasil disimpan. |
| **Verifikasi** | Test import 3 soal sekaligus → semua langsung tersimpan di DB, muncul di list builder tanpa perlu klik "Simpan" per soal, ujian bisa diikuti tanpa error "belum memiliki soal" |
| **Pelajaran** | Untuk fitur "import batch", asumsikan user mengharapkan save-otomatis — bukan staging lokal. Feedback toast jumlah soal yang berhasil disimpan memberikan konfirmasi jelas |
| **Log Keyword** | `handleImport`, `examApi.addQuestion`, `Promise.all`, `batch import`, `save` |
| **Deploy** | `cd react-app && npm run build` → copy `dist/` ke `public/react-app/` |

## Fix #8 — Feedback Format Saat Parse Gagal (User Awareness)
**Tanggal:** 2026-09-09
**Status:** ✅ LIVE

| Item | Detail |
|---|---|
| **File** | `react-app/src/pages/ExamBuilder.jsx` |
| **Masalah** | Saat parser gagal mem-parse teks input, user hanya mendapat pesan "Tidak ada soal yang berhasil diparse. Periksa format teks." tanpa informasi apa yang salah — user tidak tahu format apa yang harus dipakai |
| **Akar** | Parser selama ini swallow semua baris yang tidak dikenali tanpa melapor. Tidak ada mekanisme untuk memberi tahu user bagian mana dari inputnya yang gagal diproses |
| **Fix** | 1. Tambah variabel `unparsedLines` di dalam `parseQuestionsFromText()` yang menampung baris-baris tidak dikenali.<br>2. Tambah fungsi `isSectionKeyword(upper)` untuk mendeteksi baris yang seharusnya dikenali tapi gagal — mengidentifikasi apakah baris tersebut memang keyword section tapi syntax-nya beda.<br>3. Kembalikan `{questions, unparsedLines}` sebagai object, bukan array polos.<br>4. Tambah state `unparsedLines` di component.<br>5. Di `handleParse()`: bila questions kosong DAN unparsedLines ada, tampilkan pesan: "Format tidak dikenali. Baris yang tidak terbaca: '...'" + hint format.<br>6. Di UI modal: tambah section amber `unparsedLines` yang menampilkan baris-baris bermasalah (maks 5) sebagai `<ul>` dengan font-mono. |
| **Verifikasi** | Test dengan input format salah → parse gagal, muncul pesan error + daftar baris yang tidak terbaca. Test dengan format benar → pesan error kosong, soal muncul di preview. |
| **Pelajaran** | Parsing yang robust harus two-way: selain mengenali format yang benar, juga bisa melapor baris mana yang tidak dikenali — ini jauh lebih membantu user daripada pesan error generik |
| **Log Keyword** | `parseQuestionsFromText`, `unparsedLines`, `isSectionKeyword`, `feedback`, `handleParse` |
| **Deploy** | `cd react-app && npm run build` → copy `dist/` ke `public/react-app/` |

## Fix #7 — Parser Gagal saat "NOMOR" dan "SOAL:" di Baris Terpisah dengan Spasi Awal
**Tanggal:** 2026-09-09
**Status:** ✅ LIVE

| Item | Detail |
|---|---|
| **File** | `react-app/src/pages/ExamBuilder.jsx` |
| **Masalah** | Teks input dari PDF/Word yang di-copy paste memiliki format: `Nomor 1` di baris terpisah, lalu ` Soal: ...` (dengan spasi di depan). Parser tidak mengenali ` Soal:` karena kondisi `upper.startsWith('SOAL:')` memerlukan tanpa spasi di awal. Hasil: semua teks masuk ke `body`, choices dan kunci jawaban tidak terbaca, parser return kosong. |
| **Akar** | (1) `text.split('\n').map(l => l.trimEnd())` tidak men-trim spasi di awal baris — baris tetap punya leading whitespace dari copy-paste. (2) Kondisi `upper.startsWith('SOAL:')` gagal ketika baris berupa ` Soal:` (spasi di depan). |
| **Fix** | (1) Ganti `trimEnd()` → `trim()` agar semua baris bersih dari whitespace kedua sisi. (2) Tambah handler baru untuk baris `NOMOR N` saja (tanpa `Soal:`): deteksi regex `/^NOMOR\s*\d+$/`, panggil `finishQuestion()`, reset state, set `phase='question'`. |
| **Verifikasi** | Test dengan teks user: 2 soal dengan format `Nomor 1\n\n\n Soal: ...` + pilihan A-E + `Kunci Jawaban: A` → parser berhasil ekstrak 2 soal, choices terisi penuh, jawaban benar sesuai. |
| **Pelajaran** | Copy-paste dari PDF/Word sering introduces leading whitespace yang tidak terlihat — selalu `trim()` bukan `trimEnd()` saat parse baris teks bebas. Format "NOMOR X" dan "SOAL:" bisa terpisah di baris berbeda pada output PDF. |
| **Log Keyword** | `parseQuestionsFromText`, `trim`, `startsWith`, `leading whitespace` |
| **Deploy** | `cd react-app && npm run build` → copy `dist/` ke `public/react-app/` |

## Fix #6 — Parser Import Soal Gagal Mencocokkan Pilihan dan Kunci Jawaban
**Tanggal:** 2026-09-08
**Status:** ✅ LIVE

| Item | Detail |
|---|---|
| **File** | `react-app/src/pages/ExamBuilder.jsx` |
| **Masalah** | Saat import soal via teks, pilihan jawaban (A/B/C/D/E) dan kunci jawaban tidak terbaca — semua hasil parsing kosong atau salah |
| **Akar** | 3 bug sekaligus: (1) regex pilihan `/^([A-F])\.\s*(.+)/i` gagal karena baris masih punya spasi di depan (`  A. 3`) — `^` anchored ke awal tanpa allow whitespace; (2) kondisi deteksi kunci jawaban hanya cocok `KUNCI JAWABAN:` (dengan spasi) atau `JAWABAN:` eksak, tapi tidak menhandle `KUNCIJAWABAN:` (tanpa spasi) atau `JAWABANBENAR:`; (3) regex extractor `^(KUNCI\s+)?JAWABAN:\s*` menggunakan `\s+` (harus ada spasi) sehingga `KUNCIJAWABAN:` tidak ter-extract, dan `JAWABAN\s+BENAR` juga memerlukan spasi wajib |
| **Fix** | 1. Ganti regex pilihan: `/^([A-F])\.\s*(.+)/i` → `/^\s*([A-F])\.\s*(.+)/i` (allow leading whitespace).<br>2. Tambah helper `hasAnswerKeyword(pattern)` yang strip-spasi dari `upper` lalu `includes(pattern)` — menhandle `KUNCIJAWABAN:` dan `JAWABANBENAR:` tanpa spasi.<br>3. Ganti `\s+` jadi `\s*` di regex extract jawaban: `^(KUNCI\s*)?JAWABAN:\s*` dan `^(JAWABAN\s*BENAR|KEY\s*ANSWER):\s*`. |
| **Verifikasi** | Test 4 skenario: (1) format standar dengan spasi di pilihan → choices terisi penuh, jawaban B benar; (2) `KUNCIJAWABAN:` tanpa spasi → jawaban B terbaca; (3) `JAWABAN BENAR:` → jawaban C terbaca; (4) multi-soal → kedua soal ter-parse dengan benar |
| **Pelajaran** | Regex `^` anchored ke awal string rawan gagal kalau input punya leading whitespace — selalu `trim()` atau allow `\s*` di pattern. Juga, keyword detection harus robust terhadap variasi whitespace (spasi vs tanpa spasi) — gunakan `replace(/\s+/g,'')` sebelum match |
| **Log Keyword** | `parseQuestionsFromText`, `regex`, `startsWith`, `KUNCIJAWABAN`, `\s*`, `\s+` |
| **Deploy** | `cd react-app && npm run build` → copy `dist/` ke `public/react-app/` |

## Fix #5 — Typo PEMBHASAN di Parser Import Soal
**Tanggal:** 2026-09-08
**Status:** ✅ LIVE

| Item | Detail |
|---|---|
| **File** | `react-app/src/pages/ExamBuilder.jsx` |
| **Masalah** | Saat import soal via teks, pembahasan (explanation) tidak pernah tersimpan karena pencocokan baris "PEMBAHASAN:" gagal |
| **Akar** | Ada typo penulisan: `startsWith('PEMBHASAN:')` dan regex `/^PEMBHASAN:\s*/i` — huruf A setelah B seharusnya ada dua (PEMBA**H**ASAN), tapi ditulis PEMB**H**ASAN (hilang satu 'A') |
| **Fix** | Ganti `PEMBHASAN` → `PEMBAHASAN` di 2 tempat: check `startsWith()` (line 72) dan regex `line.replace()` (line 74) |
| **Verifikasi** | Parser test dengan teks multi-soal + pembahasan → explanation terisi benar untuk setiap soal |
| **Pelajaran** | Istilah bahasa Indonesia (pembahasan) sering lupa dikunci 'a' — selalu test parser dengan input bahasa asli, bukan English alias |
| **Log Keyword** | `PEMBAHASAN`, `startsWith`, `parseQuestionsFromText` |
| **Deploy** | `npm run build` → copy `dist/` ke `public/react-app/` |

## Fix #4 — Batch Question Import dari Teks (Modal Parse)
**Tanggal:** 2026-09-08
**Status:** ✅ LIVE

| Item | Detail |
|---|---|
| **File** | `react-app/src/pages/ExamBuilder.jsx` |
| **Masalah** | Soalkan harus ditambahkan satu per satu secara manual — tidak praktis untuk import banyak soal sekaligus dari format teks |
| **Akar** | Tidak ada parser untuk format teks soal baku (Nomor + Soal + Pilihan + Kunci + Pembahasan) |
| **Fix** | 1. Tambah state `showImport`, `importText`, `parsedQuestions`, `parseError`<br>2. Tambah fungsi `parseQuestionsFromText()` — state machine yang mengenali baris "Nomor N Soal:", "Pilihan Jawaban:", "Kunci Jawaban: X", "Pembahasan:" beserta variasi whitespace dan label pilihan (A./B./C./D./E.)<br>3. Tambah tombol "+ Import Soal" berdampingan dengan "+ Tambah Soal"<br>4. Tambah modal overlay dengan textarea, tombol Parse, preview soal yang terurai, dan tombol Import yang menambah ke state `questions`<br>5. Build Vite di-rebuild ke `public/react-app/` |
| **Verifikasi** | Paste teks format contoh → klik Parse → tampil preview soal �� klik Import → soal muncul di builder → Simpan Semua menyimpan ke DB → reload halaman tetap ada |
| **Pelajaran** | State machine sederhana (track `phase` per section) cukup untuk parsing teks ujian tanpa library eksternal |
| **Log Keyword** | `parseQuestionsFromText`, `showImport`, `batch import`, `modal`, `useState` |
| **Deploy** | `npm run build` → copy `dist/` ke `public/react-app/` |

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
