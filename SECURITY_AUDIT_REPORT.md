# SECURITY AUDIT REPORT — ski_new

## Ringkasan Eksekutif (5–10 poin)
1) Akses dokumen reviu sempat bisa diunduh oleh user login mana pun (IDOR) via `download.php`. Sudah dipatch dalam audit ini dengan verifikasi assignment per reviu.
2) Beberapa halaman admin/modul CRUD masih menerima POST/GET tanpa proteksi CSRF (kebijakan, risiko, self-assessment, pengguna). Ini membuka risiko perubahan data via request pihak ketiga.
3) Beberapa query masih menggunakan string concatenation dan sanitasi manual. Ini meningkatkan risiko SQL injection dan maintainability yang rendah.
4) Ada output HTML/ekspor yang menyisipkan data DB tanpa escaping sehingga memungkinkan XSS (khususnya pada export HTML/Word/Excel).
5) `display_errors` diaktifkan pada file produksi tertentu yang dapat membocorkan informasi sensitif.
6) Kredensial SMTP masih hard-coded di `config_mail.php` dalam webroot; perlu dipindah ke env dan/atau dilindungi ketat.
7) Role/permission sudah diperkuat dengan `bootstrap.php`, namun ada helper PHP di webroot yang masih bisa diakses langsung jika server tidak memblokirnya.
8) Tidak ada kebijakan timeout/idle session dan audit log untuk aksi admin penting.

---

## Temuan Per Kategori

### AUTH & AUTHZ
**F-001 (CRITICAL) — IDOR Download Dokumen Reviu**
- Risiko: User login dapat mengunduh dokumen reviu yang tidak ditugaskan kepadanya.
- Dampak: Kebocoran dokumen sensitif lintas unit.
- Cara exploit (tingkat tinggi): Tebak ID dokumen pada `download.php?id=...`.
- Lokasi: `download.php` (sebelum patch).
- Rekomendasi: Wajib cek assignment reviu sebelum download.
- Rencana patch: Tambahkan `review_require_access()` sebelum melayani file.
- Status: **FIXED** (patch diterapkan di `download.php`).

**F-002 (HIGH) — CSRF di Modul CRUD Admin**
- Risiko: CSRF memungkinkan pihak ketiga membuat/mengubah/menghapus data saat admin login.
- Dampak: Manipulasi data kebijakan, risiko, self-assessment, dan pengguna.
- Cara exploit: Admin membuka halaman berbahaya yang melakukan POST/GET ke aplikasi.
- Lokasi: `kebijakan.php`, `risiko.php`, `self_assessment.php`, `pengguna.php` (aksi tambah/edit/delete tanpa token).
- Rekomendasi: Tambahkan CSRF token untuk semua form POST dan ubah aksi delete GET menjadi POST + CSRF.
- Rencana patch: Tambah helper CSRF terpusat dan inject hidden token ke semua form.

**F-003 (MEDIUM) — Helper/Library di Webroot Dapat Diakses**
- Risiko: File helper PHP di webroot bisa dieksekusi langsung (tanpa UI), menambah attack surface.
- Dampak: Informasi internal, error leakage, atau misuse fungsi.
- Lokasi: `pelaporan_helpers.php`, `review_export_helpers.php`, `chr_helpers.php`, `early_warning_helpers.php`.
- Rekomendasi: Pindahkan ke `/includes` atau blok akses langsung via `.htaccess`/nginx.
- Rencana patch: Tambahkan rule deny untuk `*_helpers.php` atau pindahkan file.

### CSRF
**F-004 (HIGH) — Delete via GET Tanpa CSRF**
- Risiko: Link GET dapat dieksploitasi via CSRF/drive-by.
- Dampak: Penghapusan data tanpa persetujuan admin.
- Lokasi: `kebijakan.php` (`?delete_kebijakan=`), `pengguna.php` (`?delete_user=`), `risiko.php`, `self_assessment.php`.
- Rekomendasi: Ganti ke POST, sertakan CSRF token.
- Rencana patch: Tombol delete jadi form POST dengan token + konfirmasi.

### SQL Injection
**F-005 (MEDIUM) — String Concatenation di Query CRUD**
- Risiko: Sanitasi manual raw string berisiko dan mudah lupa.
- Dampak: Potensi SQL injection jika ada celah di sanitasi/encoding.
- Lokasi: `kebijakan.php` (INSERT/UPDATE dengan interpolasi), `pengguna.php` (SELECT by id dengan interpolasi), `risiko.php`, `self_assessment.php`.
- Rekomendasi: Gunakan prepared statement untuk semua query dinamis.
- Rencana patch: Refactor per modul menggunakan `$conn->prepare()`.

### XSS
**F-006 (MEDIUM) — Output HTML/Export Tanpa Escaping**
- Risiko: Data DB dieksekusi sebagai HTML/JS pada export (HTML/Word/Excel) atau tampilan.
- Dampak: Stored XSS saat file export dibuka di browser atau viewer.
- Lokasi: `kebijakan.php` (export html/word/excel), `risiko.php`, `self_assessment.php` (pola serupa).
- Rekomendasi: Escape output untuk semua field dari DB.
- Rencana patch: Gunakan `htmlspecialchars()` untuk semua output.

### FILE UPLOAD
**F-007 (MEDIUM) — Validasi Upload Belum Konsisten**
- Risiko: Beberapa upload sudah aman, namun belum konsisten di seluruh modul.
- Dampak: File berbahaya lolos jika MIME spoofing tidak dicegah.
- Lokasi: `login.php` (upload publik), `pelaporan.php`, `review.php`.
- Rekomendasi: Tambahkan pemeriksaan `finfo`/`mime_content_type`, whitelist ekstensi, random filename, dan simpan di luar webroot bila memungkinkan.
- Rencana patch: Hardening upload per modul, plus deny execution sudah ada.

### DIRECTORY EXPOSURE
**F-008 (LOW) — File Sensitif di Webroot**
- Risiko: `config_mail.php` berisi kredensial SMTP berada di root.
- Dampak: Kebocoran kredensial jika server salah konfigurasi.
- Lokasi: `config_mail.php`.
- Rekomendasi: Pindahkan ke env atau `config/`, tambah deny rule server.
- Rencana patch: Move + update include + update `.htaccess`/nginx.

### ERROR HANDLING
**F-009 (LOW) — display_errors Aktif**
- Risiko: Info sensitif bocor lewat error.
- Dampak: Leak path, query, stack trace.
- Lokasi: `dashboard.php`, `pengguna.php`.
- Rekomendasi: Matikan di production, gunakan logging ke file.
- Rencana patch: Controlled by env (`APP_ENV`).

### INPUT VALIDATION
**F-010 (MEDIUM) — Validasi Field Tidak Konsisten**
- Risiko: Data invalid masuk DB; potensi logic bypass.
- Dampak: Data kotor, error runtime.
- Lokasi: `kebijakan.php`, `risiko.php`, `self_assessment.php`, `pengguna.php`.
- Rekomendasi: Centralize validation, type casting ketat, length limit.
- Rencana patch: Buat helper validasi + sanitasi.

### LOGGING & AUDIT
**F-011 (MEDIUM) — Audit Trail Terbatas**
- Risiko: Sulit tracking perubahan admin.
- Dampak: Investigasi insiden sulit, compliance lemah.
- Lokasi: Sebagian `pelaporan_log`, tapi tidak untuk CRUD pengguna/kebijakan/risiko.
- Rekomendasi: Tambah audit log admin CRUD.
- Rencana patch: Table `audit_log`, log action/actor/time.

### PASSWORD SECURITY
**F-012 (MEDIUM) — Kolom password plaintext masih ada**
- Risiko: Backward-compatibility masih memeriksa `password` plain.
- Dampak: Password raw mungkin tersimpan.
- Lokasi: `login.php` (fallback plain), `pengguna.php` (set password kosong tapi kolom tetap ada).
- Rekomendasi: Migrasi penuh ke `password_hash`, hapus/abaikan kolom plaintext.
- Rencana patch: Migrate dan drop `password` column setelah update.

### BUSINESS LOGIC
**F-013 (MEDIUM) — IDOR Potential di Aksi Berbasis ID**
- Risiko: Akses data milik pihak lain jika role check tidak ketat.
- Dampak: Kebocoran data lintas unit.
- Lokasi: beberapa aksi `review.php`, `pelaporan_detail.php`, `download.php` (fixed).
- Rekomendasi: Gunakan guard `review_require_access` untuk semua aksi berbasis `rid`/`id`.
- Rencana patch: Centralize enforcement di helper.

### DEPLOY READINESS
**F-014 (LOW) — Webroot tidak dipisah (/public)**
- Risiko: File non-entrypoint tetap web-accessible.
- Dampak: Attack surface lebih luas.
- Lokasi: Struktur repo.
- Rekomendasi: Re-structure ke `/public` + server config.
- Rencana patch: Hardening plan 1–2 minggu.

---

## Tabel Ringkas Temuan
| ID | Severity | Area | File | Status |
|---|---|---|---|---|
| F-001 | Critical | AuthZ/IDOR | `download.php` | Fixed (patch) |
| F-002 | High | CSRF | `kebijakan.php`, `pengguna.php`, `risiko.php`, `self_assessment.php` | Open |
| F-003 | Medium | AuthZ/Attack Surface | helper PHP di webroot | Open |
| F-004 | High | CSRF (GET delete) | `kebijakan.php`, `pengguna.php`, `risiko.php`, `self_assessment.php` | Open |
| F-005 | Medium | SQL Injection | `kebijakan.php`, `pengguna.php`, `risiko.php`, `self_assessment.php` | Open |
| F-006 | Medium | XSS | `kebijakan.php` (export), `risiko.php`, `self_assessment.php` | Open |
| F-007 | Medium | File Upload | `login.php`, `pelaporan.php`, `review.php` | Partial |
| F-008 | Low | Secrets Exposure | `config_mail.php` | Open |
| F-009 | Low | Error Handling | `dashboard.php`, `pengguna.php` | Open |
| F-010 | Medium | Input Validation | multiple | Open |
| F-011 | Medium | Logging/Audit | multiple | Open |
| F-012 | Medium | Password | `login.php`, `pengguna.php` | Open |
| F-013 | Medium | Business Logic | `review.php` (per rid), `download.php` | Partial |
| F-014 | Low | Deploy | repo structure | Open |

---

## Rekomendasi Prioritas 1–2–3
1) **P1 — CSRF Hardening (High)**: Tambahkan CSRF token dan ubah delete GET ? POST di semua modul admin CRUD.
2) **P2 — SQL Injection Mitigation (Medium)**: Refactor query CRUD menjadi prepared statements di kebijakan/risiko/self_assessment/pengguna.
3) **P3 — XSS Mitigation (Medium)**: Escape semua output pada export HTML/Word/Excel.

---

## Catatan Lokal vs Production
- **Local (XAMPP)**: `display_errors` boleh aktif selama development saja.
- **Production (aaPanel/Nginx)**: pastikan deny access untuk `config_mail.php`, `*_helpers.php`, dan folder non-public; gunakan env untuk kredensial.
- **CSP**: sudah report-only untuk localhost/dev, enforce untuk production.

---

## Patch Kritis yang Sudah Diimplementasikan
- `download.php`: menambahkan `review_require_access()` untuk mencegah IDOR dokumen reviu.


## Daftar File Per Rekomendasi
- CSRF hardening: kebijakan.php, pengguna.php, isiko.php, self_assessment.php, (opsional) includes/csrf.php
- Prepared statements CRUD: kebijakan.php, pengguna.php, isiko.php, self_assessment.php
- XSS export escaping: kebijakan.php, isiko.php, self_assessment.php
- Block helper direct access: .htaccess, deploy/nginx_hardening_snippet.conf (atau pindahkan pelaporan_helpers.php, eview_export_helpers.php, chr_helpers.php, early_warning_helpers.php ke /includes)
- Secrets in env: config_mail.php, config/env.php, .env.example
- Error handling by env: dashboard.php, pengguna.php, includes/security_headers.php
- Audit log: pengguna.php, kebijakan.php, isiko.php, self_assessment.php, (baru) includes/audit_log.php
- Session timeout: includes/session_hardening.php, login.php

