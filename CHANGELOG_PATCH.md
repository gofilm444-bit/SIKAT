# CHANGELOG_PATCH

## 2026-01-26 � CRUD Prepared Statements Refactor

### Ringkas
- Migrasi query CRUD dari string concatenation ke prepared statements untuk mencegah SQL injection.
- Menjaga flow UI tetap sama (list/add/edit/delete/search).

### File diubah
- `kebijakan.php`
- `risiko.php`
- `self_assessment.php`
- `pengguna.php`

### Cara test (manual)
1) Buka setiap modul: kebijakan/risiko/self_assessment/pengguna dan pastikan list tampil.
2) Tambah data baru, simpan, lalu cek muncul di list.
3) Edit data yang ada, simpan, dan cek perubahan.
4) Hapus data, pastikan hilang dari list.
5) Coba filter/search (jika ada) masih berfungsi.

### Status test
- Manual UI smoke test belum dijalankan di sini (butuh DB + browser). Mohon jalankan langkah di atas.

## Security Sweep Batch #1

### Ringkas
- Prepared statements untuk query dinamis di review (status update, count, list by rid).
- IDOR guard untuk akses reviu berbasis id (non-admin harus assigned).
- Secrets SMTP dipindah ke env (config_mail.php wrapper).
- Tambah dokumen hasil auto-scan: SECURITY_SWEEP_FINDINGS.md.

### File diubah
- `review.php`
- `config_mail.php`
- `.env.example`
- `SECURITY_SWEEP_FINDINGS.md`

### Cara test (manual)
1) Buka review.php dengan role admin dan non-admin; pastikan non-admin hanya bisa akses reviu yang ditugaskan.
2) Uji action CHR create/delete, pastikan status reviu berubah sesuai aturan.
3) Jalankan skenario login & akses (tidak ada perubahan UI).
4) Pastikan SMTP env terbaca (set APP_SMTP_* di .env).

### Catatan
- Temuan IN list diselesaikan di Security Sweep Batch #2.

## Security Sweep Batch #2

### Ringkas
- Prepared statements untuk dynamic IN list di cron early warning.
- Prepared statements untuk query detail reviu di laporan_export (hapus concatenation).
- Review IDOR endpoint export/detail: sudah ada guard assignment/role, tidak perlu perubahan tambahan.

### File diubah
- `cron/early_warning.php`
- `laporan_export.php`

### Cara test (manual)
1) Jalankan cron early warning (CLI) dan pastikan output normal (tidak ada error query).
2) Akses export laporan (laporan_export.php?rid=ID) sebagai role yang diizinkan, pastikan file terunduh.
3) Coba akses export dengan user non-assign -> harus 403.


## Security Sweep Batch #3

### Ringkas
- Semua lampiran pelaporan dialihkan ke gateway `attachment_download.php` dengan guard login + role + status.
- Akses langsung ke `/uploads/` dan `/upload/` diblokir (Apache + Nginx).
- Validasi path lampiran dengan realpath + base dir whitelist.

### File diubah
- `attachment_download.php`
- `login.php`
- `pelaporan_detail.php`
- `uploads/.htaccess`
- `upload/.htaccess`
- `deploy/nginx_hardening_snippet.conf`
- `SECURITY_SWEEP_BATCH3_REPORT.md`

### Cara test (manual)
1) Buka detail pelaporan dan klik lampiran (admin/kepala_ski/direktur) -> file terbuka.
2) Akses lampiran tanpa login -> redirect ke login / 403.
3) Akses langsung URL `/uploads/...` -> 403.
4) Coba `attachment_download.php?id=0` -> 400, dan `id` tidak valid -> 404.


## Security Sweep Batch #4

### Ringkas
- Idle timeout session + session cookie cleanup.
- Rate limit login per IP+username (file-based).
- Download/attachment endpoints tetap guarded; sweep auth final.
- Tambah checklist readiness produksi.

### File diubah
- `includes/session_hardening.php`
- `login.php`
- `logout.php`
- `.htaccess`
- `deploy/nginx_hardening_snippet.conf`
- `PRODUCTION_READINESS.md`

### Cara test (manual)
1) Login lalu diamkan > 30 menit -> sesi logout otomatis.
2) Coba login gagal berulang >5x/15 menit -> diblokir/delay dengan pesan generik.
3) Akses endpoint data tanpa login -> redirect/403.


## Health Check Endpoint

### Ringkas
- Tambah endpoint admin-only `tools/health.php` (HTML/JSON).
- Cek DB, PHP extension, storage perms, env aman, dan path debug.

### File diubah
- `tools/health.php`
- `PRODUCTION_READINESS.md`
- `CHANGELOG_PATCH.md`

### Cara test (manual)
1) Login admin -> akses `/tools/health.php`.
2) Login non-admin -> akses `/tools/health.php` (403).
3) Akses `/tools/health.php?format=json` (valid JSON).


## Login Error Feedback

### Ringkas
- Tambah pesan error login yang jelas namun generik (anti-enumeration).
- Pesan tetap sama untuk username/password salah maupun delay login.

### File diubah
- `login.php`


## AJAX Login Modal

### Ringkas
- Login modal tetap terbuka saat gagal (AJAX), tanpa reload halaman.
- Fallback POST redirect tetap berjalan jika JS mati.

### File diubah
- `login.php`


## Login/Session Policy Update

### Ringkas
- Idle 30 menit + absolute 6 jam (APP_SESSION_IDLE/APP_SESSION_ABSOLUTE).
- Progressive delay + lockout per user/IP.
- Hubungi Admin link/button via APP_ADMIN_WA/APP_ADMIN_NAME.

### File diubah
- `includes/session_hardening.php`
- `login.php`
- `.env.example`
- `PRODUCTION_READINESS.md`


## Lockout Countdown Timer

### Ringkas
- Menampilkan countdown 15 menit pada lockout login (AJAX + non-AJAX).
- Tombol login dinonaktifkan selama countdown.

### File diubah
- `login.php`


## Login Modal Feedback Fix

### Ringkas
- Perbaiki visibilitas error login dan countdown lockout pada modal (AJAX + non-AJAX).
- Modal otomatis tetap terbuka saat gagal.

### File diubah
- `login.php`


## Login Modal Auth Redirect Fix

### Ringkas
- Login page auto-redirects when already authenticated.
- Auto-open modal only when not authenticated and error/hash exists.

### File diubah
- `login.php`


## Logout + Password Toggle

### Ringkas
- Perbaiki logout (destroy session + cookie + redirect ke login).
- Tambah toggle tampil/sembunyi password dengan animasi pada semua input password.

### File diubah
- `logout.php`
- `config/access_map.php`
- `login.php`
- `pengguna.php`
- `assets/js/password_toggle.js`
- `assets/css/password_toggle.css`

## Login Modal Feedback Fix (Form Binding)

### Ringkas
- Perbaiki binding JS ke form login modal agar pesan error + countdown selalu tampil.

### File diubah
- `login.php`

## Logout Fix (Cookie Cleanup)

### Ringkas
- Logout kini membersihkan session cookie di beberapa path agar sesi benar-benar terhapus.

### File diubah
- `logout.php`

## Login Fix (AJAX Credentials)

### Ringkas
- AJAX login kini mengirim cookie session (credentials same-origin) dan action form dipastikan ke login.php.

### File diubah
- `login.php`

## Login Root Cause Fix + Local Tools

### Ringkas
- Tambah fallback legacy password saat password_hash tidak cocok, dengan migrasi otomatis ke bcrypt.
- Tambah tool local untuk create superadmin dan reset lockout.
- Tambah dokumentasi debug login dan kredensial local.

### File diubah
- `login.php`
- `tools/create_superadmin.php`
- `tools/reset_lockout.php`
- `.env.example`
- `PRODUCTION_READINESS.md`
- `DEBUG_LOGIN_REPORT.md`
- `LOCAL_ADMIN_CREDENTIALS.md`

## Login AJAX Fallback Fix

### Ringkas
- Jika response AJAX bukan JSON, login otomatis fallback ke submit normal (agar login tidak gagal).

### File diubah
- `login.php`

## Logout Global Handler Fix

### Ringkas
- Tambah handler logout di bootstrap agar form logout bekerja di semua halaman.

### File diubah
- `bootstrap.php`

## Logout Reliability Fix

### Ringkas
- Logout tidak lagi gagal ketika sesi/CSRF sudah kadaluarsa (tidak memblokir logout).

### File diubah
- `bootstrap.php`

## Auth Stabilization Patch

### Ringkas
- Session auth diseragamkan via $_SESSION['auth'] + adaptor legacy.
- Timeout idle/absolute pakai force_logout agar deterministik.
- Rate limit disederhanakan: per IP+username saja, tanpa IP global cap.
- Login/logout flow distabilkan (fallback POST redirect, AJAX opsional).

### File diubah
- `includes/session_hardening.php`
- `includes/auth.php`
- `bootstrap.php`
- `login.php`
- `logout.php`
- `PRODUCTION_READINESS.md`

## Logout Deterministic Fix

### Ringkas
- Logout memakai helper tunggal `force_logout_and_redirect` dan selalu menuju login.php?logged_out=1.
- Pesan info logout ditampilkan di login page.

### File diubah
- `includes/session_hardening.php`
- `bootstrap.php`
- `logout.php`
- `login.php`

## Auth Session Stabilization

### Ringkas
- Satu fungsi `establish_login_session` untuk menyamakan struktur session auth.
- Logout deterministik via `force_logout_and_redirect` + debug log lokal.
- Password change menyelaraskan session auth.

### File diubah
- `includes/session_hardening.php`
- `login.php`
- `logout.php`
- `bootstrap.php`
- `pengguna.php`
- `DEBUG_AUTH_STABILITY_REPORT.md`

## Session Lock Release Fix

### Ringkas
- Session lock dilepas sebelum sleep/login delay dan sebelum streaming/export untuk mencegah logout tertahan.
- Tambah helper `session_release()` dan laporan `SESSION_LOCK_REPORT.md`.

### File diubah
- `includes/session_hardening.php`
- `login.php`
- `attachment_download.php`
- `download.php`
- `laporan_export.php`
- `chr_export.php`
- `chr_export_pdf.php`
- `dokumen_export.php`
- `verifikasi_export.php`
- `SESSION_LOCK_REPORT.md`


## Dashboard Profile Dropdown

### Ringkas
- Added profile dropdown in dashboard top-left (Logout, Pengaturan, Pengguna, Penerima Email).
- Moved Pengguna and Penerima Email out of main menu into profile dropdown.

### File diubah
- `dashboard.php`
- `navbar.php`
- `settings.php`
- `logout.php`
- `CHANGELOG_PATCH.md`

## Remove Super Admin Username/Password Box

### Ringkas
- Removed the Super Admin "Update Username & Password" box from user management (duplicate of password edit flow).

### File diubah
- `pengguna.php`
- `CHANGELOG_PATCH.md`

## Dashboard Auditee Access

### Ringkas
- Allow auditee roles to access dashboard without "Akses ditolak".

### File diubah
- `dashboard.php`
- `CHANGELOG_PATCH.md`

## Auditee Dashboard Redirect

### Ringkas
- Redirect auditee roles from dashboard to review.php.

### File diubah
- `dashboard.php`
- `CHANGELOG_PATCH.md`

## Review Logout Link

### Ringkas
- Restored Logout link in review dropdown using logout.php so auditee can logout without dashboard.

### File diubah
- `review.php`
- `CHANGELOG_PATCH.md`

## Footer SIKAT

### Ringkas
- Added footer "© {year} SIKAT – Team IT Poltekkes Ternate" to pages that lacked it.

### File diubah
- `chr_export_view.php`
- `dashboard.php`
- `kebijakan.php`
- `laporan_export.php`
- `mail_recipients.php`
- `pelaporan_detail.php`
- `pengguna.php`
- `risiko.php`
- `self_assessment.php`
- `settings.php`
- `CHANGELOG_PATCH.md`

## Security Hotfix Batch P0/P1

### Ringkas
- Pindahkan dokumen kredensial dari webroot dan tambahkan instruksi rotasi di storage/private.
- Blokir akses HTTP ke /tools dan tambahkan guard APP_ENV=local + token.
- Blokir akses langsung ke /upload dan /uploads (Apache + Nginx).

### File diubah
- `LOCAL_ADMIN_CREDENTIALS.md`
- `.gitignore`
- `tools/.htaccess`
- `upload/.htaccess`
- `uploads/.htaccess`
- `tools/create_superadmin.php`
- `tools/reset_lockout.php`
- `tools/health.php`
- `tools/security_selfcheck.php`
- `deploy/nginx_hardening_snippet.conf`
- `PRODUCTION_READINESS.md`
- `SECURITY_AUDIT_UPDATE.md`
- `CHANGELOG_PATCH.md`

### File baru (private)
- `storage/private/LOCAL_ADMIN_CREDENTIALS.md`
- `storage/private/ROTATE_ADMIN_PASSWORD_INSTRUCTIONS.md`

## Tools Forbidden UX

### Ringkas
- Improved tools forbidden response messaging (still 403) with minimal HTML/JSON.
- Added minimal 403 message for blocked upload directories.

### File diubah
- `tools/_deny.php`
- `tools/create_superadmin.php`
- `tools/reset_lockout.php`
- `tools/health.php`
- `tools/security_selfcheck.php`
- `upload/.htaccess`
- `uploads/.htaccess`
- `CHANGELOG_PATCH.md`

## UI Batch #1

### Ringkas
- Tambah base UI stylesheet untuk konsistensi tombol/alert/empty state/focus.
- Tabel CRUD non-bootstrap kini responsif via wrapper.
- Empty state dan alert dibuat lebih konsisten di halaman utama.
- Tambah label aksesibilitas dasar untuk form utama.

### File diubah
- `assets/css/ui_base.css`
- `dashboard.php`
- `login.php`
- `review.php`
- `pelaporan.php`
- `pelaporan_detail.php`
- `mail_recipients.php`
- `settings.php`
- `pengguna.php`
- `kebijakan.php`
- `risiko.php`
- `self_assessment.php`
- `CHANGELOG_PATCH.md`

## UI Batch #2

### Ringkas
- Unified topbar + profile dropdown via includes/topbar.php across key pages.
- Decluttered main menu and added active state in navbar.

### File diubah
- `includes/topbar.php`
- `assets/css/ui_base.css`
- `navbar.php`
- `dashboard.php`
- `review.php`
- `pelaporan.php`
- `pelaporan_detail.php`
- `mail_recipients.php`
- `settings.php`
- `CHANGELOG_PATCH.md`

## UI Batch #3

### Ringkas
- Unified flash messages via shared include.
- Empty states extended across review tabs and mail recipients.
- Added loading state for key actions (export/submit) and improved dropdown a11y.

### File diubah
- `includes/flash.php`
- `includes/topbar.php`
- `assets/css/ui_base.css`
- `pelaporan.php`
- `review.php`
- `mail_recipients.php`
- `pengguna.php`
- `kebijakan.php`
- `risiko.php`
- `self_assessment.php`
- `login.php`
- `dashboard.php`
- `CHANGELOG_PATCH.md`

## UI Batch #3 (Final polish + branding)

### Ringkas
- Flash helper diperketat agar tetap aman namun mendukung teks tebal untuk highlight.
- Branding SIKAT diterapkan di topbar/login/profile/favicons (ikon-only).
- Perbaikan kecil pada export HTML agar tidak menyisipkan include favicon.

### File diubah
- `includes/flash.php`
- `includes/topbar.php`
- `includes/head_favicon.php`
- `login.php`
- `kebijakan.php`
- `risiko.php`
- `self_assessment.php`
- `pelaporan.php`
- `asset/logo-sikat-full.png`
- `asset/logo-sikat-icon.png`
- `asset/favicon-16.png`
- `asset/favicon-32.png`
- `asset/apple-touch-icon.png`
- `CHANGELOG_PATCH.md`

### UI Hotfix
- Ganti logo topbar ke logo Poltekkes Ternate.
