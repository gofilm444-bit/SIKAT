# CHANGELOG_PATCH

## 2026-01-26 — CRUD Prepared Statements Refactor

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
- Added footer "Â© {year} SIKAT â€“ Team IT Poltekkes Ternate" to pages that lacked it.

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

## 2026-06-24 09:37:25 - Modernisasi Dashboard Eksekutif SKI

### File Diubah

* dashboard.php
* includes/topbar.php
* assets/css/ui_base.css
* public/assets/css/ui_base.css
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Menambahkan KPI SKI: Total Review Internal, Rekomendasi Aktif, TL Terlambat, dan Risiko Tinggi / Ekstrem.
* Merapikan hero dashboard agar lebih compact, seimbang, dan berorientasi ringkasan eksekutif kepatuhan internal.
* Memodernisasi tombol menu utama dengan ikon Bootstrap Icons tanpa mengubah URL modul lama.
* Menambahkan insight singkat berbasis data dengan fallback aman saat data belum tersedia.
* Mengubah Top 5 Kategori menjadi horizontal bar ringkas agar lebih mudah dibaca.
* Menambahkan panel Deadline Tindak Lanjut / Aktivitas Penting dengan empty state.
* Merapikan tabel 5 Laporan Terbaru: kolom kode lebih lega, waktu ringkas, ringkasan dibatasi, status badge konsisten, dan link Lihat Detail.
* Menambahkan informasi nama/role dan link Ubah Password pada dropdown profil topbar tanpa mengubah logout/session.

### Dampak

* Mempengaruhi tampilan dashboard, dropdown profil topbar, KPI review internal, KPI pelaporan, risiko, rekomendasi, deadline tindak lanjut, grafik kategori, dan tabel laporan terbaru.
* Tidak mengubah sistem login, session, role, routing utama dashboard.php, atau proses logout.

### Kebutuhan Database

* Tidak ada perubahan database.

### Checklist Pengujian

* [ ] Login berhasil
* [ ] Dashboard terbuka
* [ ] Menu utama tetap bisa dibuka
* [ ] Tidak ada fatal error
* [ ] Tidak ada file sensitif ikut berubah
* [ ] Perubahan sudah siap di-commit ke GitHub

## 2026-06-24 09:46:23 - Compact dashboard hero spacing

### File Diubah

* dashboard.php
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Mengurangi padding dan margin vertikal area hero dashboard agar bagian atas tidak terasa kosong.
* Mengatur logo SIKAT dashboard menjadi ukuran terkunci dan proporsional: compact di desktop dan lebih kecil di mobile.
* Membuat layout hero desktop lebih ringkas dengan logo di kiri serta judul, subjudul, dan sapaan di kanan.
* Merapatkan jarak antara logo, judul, subjudul, sapaan, tombol menu, garis pemisah, dan kartu statistik.

### Dampak

* Mempengaruhi tampilan bagian atas dashboard saja.
* Tidak mengubah fungsi dashboard, query database, login, session, role, logout, atau URL menu.

### Kebutuhan Database

* Tidak ada perubahan database.

### Checklist Pengujian

* [ ] Login berhasil
* [ ] Dashboard terbuka
* [ ] Menu utama tetap bisa dibuka
* [ ] Tidak ada fatal error
* [ ] Tidak ada file sensitif ikut berubah
* [ ] Perubahan sudah siap di-commit ke GitHub

## 2026-06-24 09:48:56 - Premium dashboard spacing polish

### File Diubah

* dashboard.php
* assets/css/ui_base.css
* public/assets/css/ui_base.css
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Merapikan alignment hero dashboard agar sejajar lebih baik dengan lebar konten utama dan kartu statistik.
* Mengurangi sedikit tinggi kartu statistik melalui padding, gap, dan ukuran angka yang lebih proporsional.
* Merapatkan jarak sapaan user ke tombol menu tanpa mengurangi kenyamanan klik.
* Memperkecil donut chart Distribusi Status agar lebih seimbang dengan grafik Tren Laporan.
* Merapikan badge SIKAT v2.0 pada header agar lebih modern dan proporsional dengan logo Poltekkes.

### Dampak

* Mempengaruhi tampilan dashboard dan badge versi header saja.
* Tidak mengubah fungsi dashboard, query database, login, session, role, logout, routing, atau menu.

### Kebutuhan Database

* Tidak ada perubahan database.

### Checklist Pengujian

* [ ] Login berhasil
* [ ] Dashboard terbuka
* [ ] Menu utama tetap bisa dibuka
* [ ] Tidak ada fatal error
* [ ] Tidak ada file sensitif ikut berubah
* [ ] Perubahan sudah siap di-commit ke GitHub

## 2026-06-24 09:53:24 - Perbaikan rasio logo hero dashboard

### File Diubah

* dashboard.php
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Memperbaiki CSS logo SIKAT pada hero dashboard agar tidak gepeng dengan memakai width tetap, height auto, max-height, dan object-fit contain.
* Menyesuaikan ukuran logo desktop dan mobile agar tetap compact serta sejajar dengan judul Dashboard SIKAT.

### Dampak

* Mempengaruhi tampilan logo SIKAT di area hero dashboard saja.
* Tidak mengubah fungsi dashboard, query database, login, session, role, route, logout, atau menu.

### Kebutuhan Database

* Tidak ada perubahan database.

### Checklist Pengujian

* [ ] Login berhasil
* [ ] Dashboard terbuka
* [ ] Menu utama tetap bisa dibuka
* [ ] Tidak ada fatal error
* [ ] Tidak ada file sensitif ikut berubah
* [ ] Perubahan sudah siap di-commit ke GitHub

## 2026-06-24 09:54:40 - Perbesar logo SIKAT hero dashboard

### File Diubah

* dashboard.php
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Memperbesar logo SIKAT pada hero dashboard sekitar 50% dari ukuran sebelumnya.
* Menyesuaikan kolom logo desktop serta ukuran tablet/mobile agar logo tetap proporsional dan tidak gepeng.

### Dampak

* Mempengaruhi tampilan logo SIKAT di area hero dashboard saja.
* Tidak mengubah fungsi dashboard, query database, login, session, role, route, logout, atau menu.

### Kebutuhan Database

* Tidak ada perubahan database.

### Checklist Pengujian

* [ ] Login berhasil
* [ ] Dashboard terbuka
* [ ] Menu utama tetap bisa dibuka
* [ ] Tidak ada fatal error
* [ ] Tidak ada file sensitif ikut berubah
* [ ] Perubahan sudah siap di-commit ke GitHub

## 2026-06-24 10:02:36 - Peningkatan dashboard publik SIKAT dengan media informatif

### File Diubah

* login.php
* assets/public/.gitkeep
* assets/public/videos/.gitkeep
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Menambahkan hero publik dengan CTA Buat Laporan, Lacak Pengaduan, dan Login Admin.
* Menambahkan area foto/video informatif berbasis file lokal dengan fallback placeholder jika media belum tersedia.
* Menambahkan section Informasi Layanan SIKAT berisi Pelaporan, Lacak Pengaduan, Saran & Kritik, dan Data Kebijakan.
* Menambahkan section Alur Pelaporan empat langkah.
* Merapikan Menu Umum, kartu statistik publik, form Pelaporan, form Lacak Pengaduan, dan form Saran & Kritik secara visual.

### Dampak

* Halaman publik lebih informatif dan profesional.
* Tidak ada perubahan database.
* Tidak mengubah login/session/role, route, upload, tracking, atau handler form publik.

### Kebutuhan Database

* Tidak ada perubahan database.

### Checklist Pengujian

* [ ] Halaman publik terbuka
* [ ] Login admin tetap bisa dibuka
* [ ] Form pelaporan tetap bisa submit
* [ ] Upload lampiran tetap normal
* [ ] Lacak pengaduan tetap normal
* [ ] Saran & kritik tetap normal
* [ ] Jika media tidak ada, placeholder tampil normal
* [ ] Jika foto/video ditambahkan, media tampil normal
* [ ] Tampilan responsif di desktop dan mobile

## 2026-06-24 10:05:39 - Polishing portal publik SIKAT

### File Diubah

* login.php
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Memperbaiki placeholder media edukasi agar lebih menarik dengan ikon kepatuhan, background lembut, dan informasi lokasi file media.
* Menambahkan dukungan prioritas video lokal di assets/public/edukasi-sikat.mp4 serta fallback ke videos/edukasi-sikat.mp4, banner, poster, atau placeholder.
* Menyeimbangkan hero publik dengan ukuran judul dan line-height yang lebih proporsional.
* Memadatkan header publik agar konten hero lebih cepat terlihat.
* Merapikan form Pelaporan dengan pembagian Identitas Pelapor, Isi Laporan, Lampiran, dan Kirim Laporan.
* Menambahkan helper text kategori, isi laporan, dan lampiran agar pengguna lebih mudah memahami form.

### Dampak

* Dashboard publik tampil lebih premium, informatif, dan seimbang.
* Tidak mengubah login/session/role/logout, query database, submit pelaporan, upload lampiran, atau lacak pengaduan.

### Kebutuhan Database

* Tidak ada perubahan database.

### Checklist Pengujian

* [ ] Halaman publik terbuka
* [ ] Login admin tetap bisa dibuka
* [ ] Form pelaporan tetap bisa submit
* [ ] Upload lampiran tetap normal
* [ ] Lacak pengaduan tetap normal
* [ ] Saran & kritik tetap normal
* [ ] Placeholder media tampil normal saat media belum ada
* [ ] Foto/video lokal tampil otomatis jika ditambahkan
* [ ] Tampilan responsif di desktop dan mobile

## 2026-06-24 10:13:11 - Final polish portal publik SIKAT

### File Diubah

* login.php
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Memadatkan header publik agar hero lebih cepat terlihat tanpa mengubah logo, judul, badge, atau tombol Login.
* Mengubah CTA hero dari Login Admin menjadi Login Petugas/Admin dengan target modal login yang sama.
* Menambahkan badge Internal pada menu E-Reviu, Manajemen Risiko, dan Self-Assessment.
* Menambahkan link Kembali ke atas setelah section Saran & Kritik.
* Memperjelas placeholder media edukasi dengan pilihan file lokal: edukasi-sikat.mp4, banner-sikat.jpg, dan poster-kepatuhan.jpg.

### Dampak

* Halaman publik tampil lebih rapi sebagai portal layanan resmi SIKAT.
* Tidak mengubah submit pelaporan, upload lampiran, anonim, tracking code, lacak pengaduan, saran & kritik, login/session/logout/role, atau query database.

### Kebutuhan Database

* Tidak ada perubahan database.

### Checklist Pengujian

* [ ] Halaman publik terbuka
* [ ] Login admin tetap bisa dibuka
* [ ] Form pelaporan tetap bisa submit
* [ ] Upload lampiran tetap normal
* [ ] Lacak pengaduan tetap normal
* [ ] Saran & kritik tetap normal
* [ ] Placeholder media tampil normal saat media belum ada
* [ ] Foto/video lokal tampil otomatis jika ditambahkan
* [ ] Link Kembali ke atas berfungsi
* [ ] Tampilan responsif di desktop dan mobile

## 2026-06-24 10:25:08 - Fitur kelola media publik SIKAT

### File Diubah

* login.php
* public_media.php
* dashboard.php
* includes/topbar.php
* deploy/migrations/20260624_101500_create_public_media.sql
* assets/public/media/.gitkeep
* assets/public/media/.htaccess
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Menambahkan halaman admin Kelola Media Publik untuk upload gambar/video edukasi, melihat daftar media, mengatur judul/caption, tipe media, urutan tampil, status aktif, dan hapus media.
* Menambahkan validasi file media publik: gambar JPG/JPEG/PNG/WEBP maksimal 5 MB dan video MP4/WEBM/MOV maksimal 30 MB.
* Menambahkan folder assets/public/media/ sebagai lokasi penyimpanan media publik serta proteksi dasar terhadap file berbahaya.
* Menambahkan migration SQL untuk tabel public_media.
* Mengubah area media halaman publik menjadi carousel/slider yang menampilkan media aktif berdasarkan sort_order.
* Menambahkan fallback aman jika tabel belum ada, file media hilang, atau belum ada media aktif.
* Menambahkan menu Kelola Media Publik untuk admin/super_admin di topbar dan dashboard.

### Dampak

* Admin/super_admin dapat mengelola media edukasi publik dari aplikasi.
* Halaman publik menampilkan carousel gambar/video yang proporsional dengan rasio 16:9.
* Tidak mengubah login/session/role/logout, submit pelaporan, upload lampiran pelaporan, lacak pengaduan, saran & kritik, atau tracking code.

### Kebutuhan Database

* Perlu menjalankan migration: deploy/migrations/20260624_101500_create_public_media.sql
* Tidak ada perubahan database langsung dari kode.

### Checklist Pengujian

* [ ] Migration public_media berhasil dijalankan
* [ ] Admin/super_admin bisa membuka Kelola Media Publik
* [ ] Upload gambar valid berhasil
* [ ] Upload video valid berhasil
* [ ] File berbahaya ditolak
* [ ] Media bisa diaktifkan/nonaktifkan
* [ ] Urutan tampil bisa diubah
* [ ] Media bisa dihapus
* [ ] Halaman publik menampilkan carousel media aktif
* [ ] Jika tabel belum ada, halaman publik tetap tidak fatal error
* [ ] Jika file media hilang, media dilewati
* [ ] Jika tidak ada media aktif, placeholder tetap tampil

## 2026-06-24 10:28:47 - Gabungkan layanan publik dan akses cepat

### File Diubah

* login.php
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Menggabungkan section Informasi Layanan SIKAT dan Menu Umum/Akses Cepat.
* Menjadikan kartu layanan sebagai akses cepat yang bisa diklik.
* Menambahkan aksi/anchor pada tiap kartu layanan.
* Menandai fitur internal dengan badge Internal.

### Dampak

* Halaman publik lebih pendek, praktis, dan tidak dobel.
* Tidak ada perubahan database.
* Tidak mengubah fungsi form atau login/session.

### Kebutuhan Database

* Tidak ada perubahan database.

### Checklist Pengujian

* [ ] Halaman publik terbuka normal
* [ ] Section akses cepat lama tidak dobel lagi
* [ ] Kartu Pelaporan mengarah ke form pelaporan
* [ ] Kartu Lacak Pengaduan mengarah ke form tracking
* [ ] Kartu Saran & Kritik mengarah ke form saran kritik
* [ ] Kartu Data Kebijakan tetap bisa dibuka
* [ ] Fitur internal mengarah ke login/halaman terkait
* [ ] Tampilan desktop rapi
* [ ] Tampilan mobile rapi
* [ ] Form pelaporan tetap normal
* [ ] Lacak pengaduan tetap normal
* [ ] Saran & kritik tetap normal

## 2026-06-24 11:24:47 - Perbaikan caption media edukasi publik

### File Diubah

* login.php
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Menghapus caption overlay pada media edukasi.
* Memindahkan judul dan caption ke bawah gambar/video.
* Menambahkan caption ringkas dengan ellipsis dua baris.
* Menambahkan fitur Baca selengkapnya untuk caption panjang dengan expand/collapse ringan.

### Dampak

* Poster/video edukasi tidak tertutup teks.
* Informasi media tetap terbaca rapi.
* Tidak ada perubahan database.

### Kebutuhan Database

* Tidak ada perubahan database.

### Checklist Pengujian

* [ ] Gambar edukasi tampil tanpa caption overlay
* [ ] Video edukasi tampil tanpa caption overlay
* [ ] Judul media tampil di bawah media
* [ ] Caption pendek tampil maksimal 2 baris
* [ ] Caption panjang bisa dibuka dengan Baca selengkapnya
* [ ] Caption bisa ditutup kembali jika menggunakan expand/collapse
* [ ] Carousel/tab media tetap berfungsi
* [ ] Upload media tetap normal
* [ ] Halaman publik tetap responsif
* [ ] Tidak ada fatal error

## 2026-06-24 11:33:26 - Perbaikan orientasi media edukasi publik

### File Diubah

* login.php
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Menambahkan deteksi orientasi gambar media edukasi dengan JavaScript vanilla.
* Menampilkan gambar landscape sebagai banner 16:9 dengan object-fit cover.
* Menampilkan gambar portrait/poster secara utuh dengan object-fit contain, background lembut, dan batas tinggi maksimal.
* Mempertahankan video pada rasio 16:9 dengan object-fit contain.
* Mempertahankan caption di bawah media tanpa overlay.

### Dampak

* Poster vertikal tampil utuh tanpa terpotong.
* Banner landscape tetap tampil penuh dan menarik.
* Video tetap proporsional.
* Tidak ada perubahan database.

### Kebutuhan Database

* Tidak ada perubahan database.

### Checklist Pengujian

* [ ] Gambar landscape tampil sebagai banner lebar
* [ ] Gambar portrait/9:16 tampil utuh tanpa terpotong
* [ ] Video tetap tampil proporsional
* [ ] Caption tetap di bawah media
* [ ] Carousel/auto swipe tetap berjalan normal
* [ ] Upload media tetap normal
* [ ] Daftar media tetap normal
* [ ] Hapus media tetap normal
* [ ] Aktif/nonaktif media tetap normal
* [ ] Tidak ada fatal error

## 2026-06-24 11:41:39 - Perapian section layanan publik dan akses internal

### File Diubah

* login.php
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Memisahkan layanan publik dan akses internal.
* Memindahkan statistik pengaduan ke section tersendiri.
* Merapikan kartu layanan, badge internal, dan layout responsif.
* Menjadikan section lebih ringkas dan tidak dobel.

### Dampak

* Halaman publik lebih rapi dan mudah dipahami.
* Fitur publik dan internal lebih jelas dibedakan.
* Tidak ada perubahan database.

### Kebutuhan Database

* Tidak ada perubahan database.

### Checklist Pengujian

* [ ] Section layanan publik tampil 4 kartu utama
* [ ] Kartu Pelaporan mengarah ke form pelaporan
* [ ] Kartu Lacak Pengaduan mengarah ke form tracking
* [ ] Kartu Saran & Kritik mengarah ke form saran kritik
* [ ] Kartu Data Kebijakan tetap berfungsi
* [ ] Akses Internal tampil terpisah
* [ ] Badge Internal tampil kecil dan rapi
* [ ] Statistik pengaduan tampil di section tersendiri
* [ ] Tampilan desktop rapi
* [ ] Tampilan mobile rapi
* [ ] Tidak ada fungsi form yang rusak

## 2026-06-24 11:48:33 - Perapian CTA hero publik

### File Diubah

* login.php
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Merapikan grup tombol CTA pada hero publik.
* Menyamakan tinggi, padding, radius, dan jarak tombol.
* Menyesuaikan spacing label, judul, deskripsi, dan tombol.
* Menambahkan responsivitas tombol untuk desktop/mobile.

### Dampak

* Hero publik lebih rapi dan profesional.
* Tidak ada perubahan fungsi.
* Tidak ada perubahan database.

### Kebutuhan Database

* Tidak ada perubahan database.

### Checklist Pengujian

* [ ] Tombol Buat Laporan mengarah ke form pelaporan
* [ ] Tombol Lacak Pengaduan mengarah ke form tracking
* [ ] Tombol Login Petugas/Admin mengarah ke login
* [ ] Tombol sejajar rapi di desktop
* [ ] Tombol tersusun rapi di mobile
* [ ] Tidak ada fungsi form yang rusak

## 2026-06-24 11:51:57 - Stabilisasi tinggi card media edukasi

### File Diubah

* login.php
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Menetapkan tinggi frame media yang stabil untuk desktop, tablet, dan mobile.
* Mempertahankan gambar landscape dengan object-fit cover.
* Menampilkan gambar portrait secara utuh dengan object-fit contain dan background hijau lembut.
* Mempertahankan video dengan object-fit contain dan controls aktif.
* Menjaga caption tetap berada di bawah media.

### Dampak

* Card media tidak berubah tinggi secara ekstrem saat carousel berpindah orientasi.
* Gambar landscape dan portrait tetap proporsional.
* Tidak ada perubahan fungsi atau database.

### Kebutuhan Database

* Tidak ada perubahan database.

### Checklist Pengujian

* [ ] Card media memiliki tinggi stabil di desktop
* [ ] Card media memiliki tinggi stabil di tablet/mobile
* [ ] Gambar landscape tampil memenuhi frame
* [ ] Gambar portrait tampil utuh tanpa terpotong
* [ ] Video tetap proporsional dan controls aktif
* [ ] Caption tetap berada di bawah media
* [ ] Carousel tetap berjalan normal
* [ ] Tidak ada fatal error

## 2026-06-24 11:58:09 - Penyesuaian proporsi hero publik 40/60

### File Diubah

* login.php
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Mengubah layout hero desktop menjadi kolom teks 40% dan media 60%.
* Membatasi lebar teks hero agar tetap nyaman dibaca.
* Menata CTA menjadi dua tombol utama dan tombol login pada baris kedua.
* Memperlebar kolom media serta menambah tinggi frame desktop secara proporsional.
* Mengubah tampilan gambar menjadi object-fit contain agar teks pada banner/poster tidak terpotong.
* Menambahkan layout responsif 45/55 untuk tablet dan satu kolom untuk mobile.

### Dampak

* Media edukasi lebih lega dan mudah dibaca.
* Hero publik lebih seimbang pada desktop, tablet, dan mobile.
* Tidak ada perubahan fungsi atau database.

### Kebutuhan Database

* Tidak ada perubahan database.

### Checklist Pengujian

* [ ] Hero desktop tampil dengan proporsi sekitar 40/60
* [ ] Hero tablet tampil proporsional
* [ ] Hero mobile tampil satu kolom
* [ ] Tombol CTA tidak saling menimpa
* [ ] Gambar edukasi tampil utuh tanpa gepeng
* [ ] Video tetap tampil proporsional
* [ ] Carousel tetap berjalan normal
* [ ] Tidak ada fatal error

## 2026-06-24 12:04:01 - Sederhanakan hero publik dan jadikan layanan sebagai akses cepat

### File Diubah

* login.php
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Menghapus tombol CTA dari hero kiri.
* Menghapus tombol Login Petugas/Admin dari hero.
* Menjadikan section Informasi Layanan SIKAT sebagai akses cepat utama.
* Merapikan spacing hero setelah tombol dihapus.
* Menegaskan kartu layanan sebagai navigasi utama.

### Dampak

* Hero publik lebih bersih dan tidak dobel.
* Akses utama pengguna berpindah ke kartu layanan.
* Tidak ada perubahan database.
* Tidak mengubah fungsi login, pelaporan, tracking, dan saran kritik.

### Kebutuhan Database

* Tidak ada perubahan database.

### Checklist Pengujian

* [ ] Hero kiri tampil tanpa tombol CTA
* [ ] Tombol Login tetap tersedia di header kanan atas
* [ ] Section layanan tampil tepat setelah hero
* [ ] Kartu Pelaporan mengarah ke form pelaporan
* [ ] Kartu Lacak Pengaduan mengarah ke form tracking
* [ ] Kartu Saran & Kritik mengarah ke form saran kritik
* [ ] Kartu Data Kebijakan tetap berfungsi
* [ ] Hero tidak menyisakan ruang kosong akibat tombol dihapus
* [ ] Tampilan desktop rapi
* [ ] Tampilan mobile rapi
* [ ] Tidak ada fungsi form yang rusak

## 2026-06-24 12:08:19 - Rapikan hero publik setelah CTA dihapus

### File Diubah

* login.php
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Menggeser teks hero lebih ke atas.
* Mengurangi tinggi/min-height hero melalui padding yang lebih compact.
* Memastikan section layanan berada tepat di bawah hero.
* Mengurangi jarak kosong antara hero dan layanan.
* Memastikan tombol hero tetap dihapus.
* Mengembalikan section Alur Pelaporan sebelum Ringkasan Pengaduan sesuai urutan halaman.

### Dampak

* Hero publik lebih compact dan tidak kosong.
* Section layanan menjadi akses cepat utama tepat setelah hero.
* Tidak ada perubahan database.

### Kebutuhan Database

* Tidak ada perubahan database.

### Checklist Pengujian

* [ ] Hero kiri tidak menampilkan tombol CTA
* [ ] Teks hero kiri berada lebih ke atas
* [ ] Tidak ada ruang kosong besar di bawah teks hero
* [ ] Section layanan tampil tepat setelah hero
* [ ] Jarak hero ke section layanan wajar
* [ ] Media kanan tetap tampil normal
* [ ] Tampilan mobile tetap rapi
* [ ] Login header tetap berfungsi
* [ ] Form pelaporan tetap normal
* [ ] Lacak pengaduan tetap normal
* [ ] Saran kritik tetap normal

## 2026-06-24 12:13:03 - Pindahkan layanan publik ke hero kiri

### File Diubah

* login.php
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Memindahkan layanan publik dan akses cepat ke dalam hero kiri.
* Mengubah kartu layanan menjadi compact action grid.
* Menghapus section layanan publik terpisah agar tidak dobel.
* Mempertahankan media edukasi di hero kanan 60%.
* Memastikan Login hanya tersedia di header.
* Memindahkan Akses Internal dan Kontak ke section kecil setelah Alur Pelaporan.

### Dampak

* Hero publik lebih fungsional.
* Halaman lebih pendek.
* Tidak ada duplikasi antara CTA dan layanan publik.
* Tidak ada perubahan database.

### Kebutuhan Database

* Tidak ada perubahan database.

### Checklist Pengujian

* [ ] Hero kiri menampilkan label, judul, deskripsi, dan akses layanan compact
* [ ] Section layanan publik besar di bawah hero sudah tidak tampil dobel
* [ ] Pelaporan mengarah ke #pelaporan
* [ ] Lacak Pengaduan mengarah ke #lacak-pengaduan
* [ ] Saran & Kritik mengarah ke #saran-kritik
* [ ] Data Kebijakan tetap berfungsi
* [ ] Login hanya ada di header kanan atas
* [ ] Media kanan tetap tampil normal
* [ ] Hero desktop rapi 40/60
* [ ] Tampilan mobile rapi
* [ ] Form pelaporan tetap normal
* [ ] Lacak pengaduan tetap normal
* [ ] Saran kritik tetap normal

## 2026-06-24 13:03:39 - Polishing akses cepat hero publik

### File Diubah

* login.php
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Merapikan jarak antara deskripsi hero dan label Layanan publik dan akses cepat.
* Memastikan seluruh tile akses cepat dapat diklik penuh.
* Menambahkan hover dan focus state ringan yang lebih profesional.
* Menyesuaikan spacing akses cepat pada tampilan mobile.

### Dampak

* Akses cepat di hero lebih rapi dan mudah digunakan.
* Tidak ada perubahan fungsi.
* Tidak ada perubahan database.

### Kebutuhan Database

* Tidak ada perubahan database.

### Checklist Pengujian

* [ ] Jarak deskripsi ke akses cepat terlihat rapi
* [ ] Seluruh area tile akses cepat bisa diklik
* [ ] Hover tile terlihat halus dan profesional
* [ ] Focus keyboard tetap terlihat
* [ ] Tampilan mobile tetap rapi
* [ ] Tidak ada fungsi form yang rusak

## 2026-06-24 13:19:00 - Tambah pengelolaan kontak publik

### File Diubah

* login.php
* dashboard.php
* includes/topbar.php
* public_contacts.php
* deploy/migrations/20260624_131408_create_public_contacts.sql
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Menambahkan fitur Kelola Kontak Publik untuk admin/super admin.
* Menambahkan migration tabel public_contacts.
* Menampilkan kontak pengelola pada halaman publik melalui section #kontak.
* Menghubungkan tombol Lihat Kontak ke section kontak publik.
* Menambahkan fallback jika tabel atau kontak aktif belum tersedia.

### Dampak

* Admin dapat mengatur informasi kontak publik.
* Pengguna publik dapat melihat kontak pengelola SIKAT.
* Tidak mengubah fungsi pelaporan, tracking, media, atau login.

### Kebutuhan Database

* Migration baru: deploy/migrations/20260624_131408_create_public_contacts.sql

### Checklist Pengujian

* [ ] Admin bisa membuka Kelola Kontak Publik
* [ ] Admin bisa menyimpan kontak
* [ ] Admin bisa mengedit kontak
* [ ] Admin bisa aktif/nonaktifkan kontak
* [ ] Halaman publik tetap terbuka normal jika tabel kontak belum ada
* [ ] Halaman publik menampilkan fallback jika kontak belum diisi
* [ ] Tombol Lihat Kontak menuju section kontak
* [ ] Tombol WhatsApp membuka wa.me jika nomor tersedia
* [ ] Tombol Email membuka mailto jika email tersedia
* [ ] Tombol Lokasi tampil jika maps_url tersedia
* [ ] Login/session/role tetap normal
* [ ] Form pelaporan tetap normal
* [ ] Lacak pengaduan tetap normal
* [ ] Saran kritik tetap normal

## 2026-06-24 13:56:55 - Tambah pengelolaan media sosial publik

### File Diubah

* login.php
* public_contacts.php
* deploy/migrations/20260624_132219_create_public_social_links.sql
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Menambahkan pengelolaan link media sosial resmi pada halaman Kelola Kontak Publik.
* Menambahkan migration tabel public_social_links untuk platform, URL, ikon, urutan, dan status aktif.
* Menampilkan media sosial aktif di section kontak dashboard publik.
* Menambahkan validasi URL http/https dan fallback aman jika tabel/link belum tersedia.
* Menjaga tampilan responsif dengan pill link sesuai gaya SIKAT.

### Dampak

* Admin dapat mengelola link media sosial resmi.
* Pengunjung publik dapat mengakses kanal resmi SIKAT/Poltekkes dari section kontak.
* Tidak mengubah fungsi pelaporan, tracking, media edukasi, atau login.

### Kebutuhan Database

* Migration baru: deploy/migrations/20260624_132219_create_public_social_links.sql

### Checklist Pengujian

* [ ] Admin bisa membuka Kelola Kontak Publik
* [ ] Admin bisa mengisi website resmi
* [ ] Admin bisa mengisi Facebook
* [ ] Admin bisa mengisi Instagram
* [ ] Admin bisa mengisi YouTube
* [ ] Admin bisa mengisi TikTok
* [ ] Link kosong tidak tampil di halaman publik
* [ ] Link tidak aktif tidak tampil di halaman publik
* [ ] URL tidak valid ditolak atau diberi pesan validasi
* [ ] Section kontak publik tampil normal
* [ ] Media sosial tampil di dashboard publik
* [ ] Klik media sosial membuka link di tab baru
* [ ] Halaman publik tidak fatal error jika tabel belum ada
* [ ] Login/session/role tetap normal
* [ ] Form pelaporan tetap normal
* [ ] Lacak pengaduan tetap normal
* [ ] Saran kritik tetap normal

## 2026-06-24 14:01:26 - Jadikan kontak publik accordion

### File Diubah

* login.php
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Menyembunyikan detail kontak secara default.
* Membuat tombol Lihat Kontak membuka/tutup section kontak.
* Menambahkan dukungan hash #kontak agar kontak otomatis terbuka.
* Mempertahankan tampilan dan data kontak yang sudah ada.

### Dampak

* Halaman publik lebih ringkas.
* Kontak tetap mudah diakses saat dibutuhkan.
* Tidak ada perubahan database.

### Kebutuhan Database

Tidak ada perubahan database

### Checklist Pengujian

* [ ] Kontak tidak langsung tampil saat halaman dibuka
* [ ] Klik Lihat Kontak membuka section kontak
* [ ] Tombol berubah menjadi Tutup Kontak
* [ ] Klik Tutup Kontak menyembunyikan section kontak
* [ ] URL #kontak otomatis membuka section kontak
* [ ] Tombol WhatsApp tetap berfungsi
* [ ] Tombol Email tetap berfungsi
* [ ] Tombol Lokasi tetap berfungsi
* [ ] Tampilan desktop rapi
* [ ] Tampilan mobile rapi
* [ ] Tidak ada fungsi form yang rusak

## 2026-06-24 14:05:29 - Tampilkan media sosial di hero publik

### File Diubah

* login.php
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Menambahkan blok “Terhubung dengan kami” di hero kiri.
* Menampilkan link media sosial aktif di bawah akses cepat.
* Menyembunyikan blok media sosial jika data kosong.
* Menambahkan validasi/fallback agar tidak fatal error.

### Dampak

* Dashboard publik lebih informatif.
* Link resmi lebih mudah ditemukan pengguna.
* Tidak mengubah fungsi pelaporan, tracking, media edukasi, atau login.

### Kebutuhan Database

Tidak ada perubahan database

### Checklist Pengujian

* [ ] Hero kiri menampilkan media sosial jika data aktif tersedia
* [ ] Blok media sosial tidak tampil jika data kosong
* [ ] Link media sosial terbuka di tab baru
* [ ] Website/Facebook/Instagram/YouTube/TikTok tampil sesuai data
* [ ] Tampilan desktop rapi
* [ ] Tampilan mobile rapi
* [ ] Tidak ada fatal error jika tabel media sosial belum ada
* [ ] Form pelaporan tetap normal
* [ ] Lacak pengaduan tetap normal
* [ ] Saran kritik tetap normal
* [ ] Login/session tetap normal

## 2026-06-24 14:29:21 - Ubah form publik menjadi modal

### File Diubah

* login.php
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Mengubah Form Pelaporan menjadi modal yang dibuka dari akses cepat Pelaporan.
* Mengubah Lacak Pengaduan menjadi modal yang dibuka dari akses cepat Lacak Pengaduan.
* Mengubah Saran & Kritik menjadi modal yang dibuka dari akses cepat Saran & Kritik.
* Menyembunyikan section panjang dari halaman utama tanpa mengubah field, submit, upload, tracking, atau feedback.
* Menambahkan pembukaan modal otomatis berdasarkan hash #pelaporan, #lacak-pengaduan, dan #saran-kritik setelah submit/redirect.

### Dampak

* Halaman publik lebih pendek dan ringkas.
* Form tetap dapat digunakan saat dibutuhkan melalui modal.
* Tidak mengubah fungsi pelaporan, tracking, media edukasi, kontak, media sosial, atau login.

### Kebutuhan Database

Tidak ada perubahan database

### Checklist Pengujian

* [ ] Klik Pelaporan membuka modal Form Pelaporan
* [ ] Klik Lacak Pengaduan membuka modal tracking
* [ ] Klik Saran & Kritik membuka modal feedback
* [ ] Section panjang form tidak tampil di halaman utama
* [ ] Submit pelaporan tetap normal
* [ ] Upload lampiran tetap normal
* [ ] Tracking code tetap tampil setelah submit pelaporan
* [ ] Lacak pengaduan tetap menampilkan hasil
* [ ] Submit saran/kritik tetap normal
* [ ] Modal terbuka kembali setelah submit/redirect sesuai hash
* [ ] Modal bisa ditutup dengan X dan Escape
* [ ] Body belakang tidak ikut scroll saat modal terbuka
* [ ] Tampilan desktop rapi
* [ ] Tampilan mobile rapi
* [ ] Login/session tetap normal

## 2026-06-24 14:40:17 - Audit dan hardening keamanan aplikasi

### File Diubah

* login.php
* assets/public/media/.gitignore
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Memperkuat upload lampiran publik dengan deteksi MIME server-side menggunakan finfo dan whitelist ekstensi.
* Memblokir ekstensi berbahaya pada lampiran publik, termasuk php, phtml, html, js, exe, sh, bat, cmd, dan svg.
* Menambahkan rate limit ringan berbasis session untuk form pelaporan, lacak pengaduan, dan saran/kritik.
* Menambahkan honeypot tersembunyi pada form publik untuk menahan bot sederhana.
* Mengganti pesan error database publik menjadi pesan generik agar detail DB tidak bocor.
* Menambahkan .gitignore pada folder assets/public/media agar file upload media publik tidak ikut commit.

### Temuan Keamanan

* Kritis: Tidak ditemukan akses kritis yang langsung bisa dieksploitasi pada area yang dipatch.
* Tinggi: Upload lampiran publik sebelumnya memakai MIME dari browser; sudah diperkuat dengan finfo dan validasi ekstensi.
* Sedang: Form publik belum memiliki throttling ringan; sudah ditambah rate limit session dan honeypot.
* Rendah: Pesan error publik berpotensi membocorkan detail database; sudah diganti generik. File media upload publik berisiko ikut git add; sudah ditambah .gitignore.

### Dampak

* Modul terdampak: halaman publik login.php, form pelaporan, lacak pengaduan, saran/kritik, dan hygiene folder media publik.
* Pengguna normal tetap dapat submit form, namun submit berulang terlalu cepat akan diminta mencoba lagi beberapa saat.
* Upload lampiran tetap berjalan untuk file valid sesuai whitelist.

### Kebutuhan Database

Tidak ada perubahan database

### Checklist Pengujian

* [ ] Login berhasil
* [ ] Logout berhasil
* [ ] Dashboard admin terbuka sesuai role
* [ ] Halaman internal tidak bisa diakses tanpa login
* [ ] Form pelaporan publik tetap bisa submit
* [ ] Upload lampiran tetap normal
* [ ] Lacak pengaduan tetap normal
* [ ] Saran & kritik tetap normal
* [ ] Kelola media publik tetap normal
* [ ] Kelola kontak publik tetap normal
* [ ] Kelola media sosial tetap normal
* [ ] Tidak ada fatal error
* [ ] Tidak ada file sensitif ikut berubah
* [ ] PHP syntax check sukses
