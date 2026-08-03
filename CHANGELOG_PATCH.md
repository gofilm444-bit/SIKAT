# CHANGELOG_PATCH

## 2026-08-03 10:09:25 - Sederhanakan sidebar Review Internal

### File Diubah

* includes/sidebar.php
* review.php
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Menghapus submenu Review Internal dari sidebar.
* Menjadikan menu Review Internal sebagai link langsung ke Jadwal Reviu.
* Mempertahankan tab Review Internal di halaman review.php.
* Menambahkan tombol Buka Daftar Jadwal pada tab detail yang belum memiliki reviu terpilih.

### Dampak

* Sidebar lebih ringkas dan tidak menduplikasi tab Review Internal.
* Jadwal menjadi titik awal pemilihan reviu sebelum membuka Penugasan, Dokumen, CHR, atau Laporan.
* Parent Review Internal tetap aktif selama pengguna berada di modul review.
* Tidak ada perubahan database, hak akses, workflow, template CHR, atau URL produksi.

### Kebutuhan Database

Tidak ada perubahan database

### Checklist Pengujian

* [ ] Sidebar hanya menampilkan menu Review Internal tanpa submenu
* [ ] Klik Review Internal membuka Jadwal
* [ ] Tab Review Internal tetap tampil di halaman review.php
* [ ] Parent Review Internal aktif pada tab Jadwal, Penugasan, Dokumen, CHR, Laporan, dan Master
* [ ] Tab detail tanpa rid menampilkan tombol Buka Daftar Jadwal
* [ ] Klik kode reviu dari Jadwal membawa rid ke tab detail
* [ ] Pindah tab setelah memilih reviu mempertahankan rid
* [ ] Master tetap sesuai hak akses
* [ ] Tidak ada fatal error
## 2026-08-03 10:03:58 - Tambahkan catatan status jadwal tanpa mengubah tahap

### File Diubah

* review.php
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Menambahkan menu Atur Catatan Status pada dropdown aksi Jadwal Reviu.
* Membuat handler khusus reviu_note_update yang hanya memperbarui kolom catatan.
* Menambahkan modal catatan status dengan status utama read-only.
* Membatasi tampilan catatan pada kolom Status agar tabel tetap rapi.

### Dampak

* Catatan kondisi kegiatan dapat diisi tanpa mengubah status utama.
* Majukan Tahap tetap menjadi pengendali status/tahap kegiatan.
* Atur Deadline dan Hapus tetap berjalan seperti sebelumnya.
* Tidak ada perubahan database, workflow CHR, template, hak akses, atau sidebar.

### Kebutuhan Database

Tidak ada perubahan database

### Checklist Pengujian

* [ ] Atur Catatan Status tampil pada dropdown aksi Jadwal
* [ ] Modal menampilkan kode, nama kegiatan, dan status utama read-only
* [ ] Simpan Catatan hanya mengirim id dan catatan
* [ ] Catatan tampil di bawah badge status
* [ ] Mengosongkan catatan menyembunyikan catatan dari tabel
* [ ] Majukan Tahap tidak berubah
* [ ] Atur Deadline tetap berfungsi
* [ ] Hapus tetap berfungsi
* [ ] Tidak ada fatal error
## 2026-08-03 09:53:39 - Hapus Atur Status dari aksi jadwal reviu

### File Diubah

* review.php
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Menghapus menu Atur Status dari dropdown aksi Jadwal Reviu.
* Menghapus modal Atur Status yang hanya dipakai oleh menu tersebut.
* Menghapus JavaScript khusus pembuka dan pengisi modal Atur Status.
* Mempertahankan Majukan Tahap sebagai pengendali status kegiatan.

### Dampak

* Pengguna tidak lagi melihat dua jalur pengubahan status pada Jadwal Reviu.
* Atur Deadline dan Hapus tetap tersedia pada menu tiga titik.
* Tidak ada perubahan database, workflow CHR, template, hak akses, atau sidebar.

### Kebutuhan Database

Tidak ada perubahan database

### Checklist Pengujian

* [ ] Menu Atur Status tidak tampil pada dropdown aksi Jadwal
* [ ] Modal Atur Status tidak dirender
* [ ] Tombol Majukan Tahap tetap tampil sesuai status dan role
* [ ] Majukan Tahap tetap mengirim action reviu_step
* [ ] Atur Deadline tetap membuka modal
* [ ] Hapus tetap membuka konfirmasi
* [ ] Tidak ada error JavaScript
* [ ] Tidak ada fatal error
## 2026-08-03 09:38:38 - Perbaiki fungsi menu aksi jadwal reviu

### File Diubah

* review.php
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Memindahkan modal Atur Status, Atur Deadline, dan Hapus agar tersedia pada tab Jadwal.
* Menambahkan nama kegiatan pada data tombol aksi untuk ditampilkan di modal.
* Memperkuat JavaScript pengisi modal agar field POST terisi lewat selector eksplisit.
* Mempertahankan handler lama untuk status, deadline, dan hapus.

### Dampak

* Menu tiga titik pada tabel Jadwal Reviu kembali berfungsi.
* Atur Status, Atur Deadline, dan Hapus tetap memakai CSRF dan validasi server lama.
* Tidak ada perubahan database, layout besar, workflow, CHR, sidebar, atau URL.

### Kebutuhan Database

Tidak ada perubahan database

### Checklist Pengujian

* [ ] Atur Status membuka modal pada tab Jadwal
* [ ] Modal status menampilkan kode, nama kegiatan, status saat ini, dan catatan
* [ ] Simpan Status mengirim action reviu_status_update
* [ ] Atur Deadline membuka modal pada tab Jadwal
* [ ] Modal deadline menampilkan kode, nama kegiatan, deadline, dan info deadline
* [ ] Simpan Deadline mengirim action reviu_deadline_update
* [ ] Hapus membuka modal konfirmasi
* [ ] Konfirmasi hapus mengirim action reviu_delete
* [ ] Tombol Batal tidak mengirim form
* [ ] Tidak ada fatal error
## 2026-08-03 09:22:33 - Sederhanakan kolom aksi jadwal reviu

### File Diubah

* review.php
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Merapikan kolom Aksi pada tabel Jadwal Reviu.
* Menampilkan aksi utama Buka, Majukan Tahap, dan menu Lainnya.
* Memindahkan Atur Status, Atur Deadline, dan Hapus ke dropdown/modal.
* Mempertahankan action POST, CSRF, dan handler backend yang sudah ada.

### Dampak

* Tabel Jadwal Reviu lebih ringkas dan mudah dibaca.
* Status, deadline, early warning, dan catatan tetap terlihat.
* Tidak ada perubahan database, workflow, hak akses, CHR, export, atau clean URL.

### Kebutuhan Database

Tidak ada perubahan database

### Checklist Pengujian

* [ ] Tombol Buka membuka jadwal/reviu lama
* [ ] Tombol Majukan Tahap tetap mengirim action lama
* [ ] Menu Lainnya membuka opsi Atur Status, Atur Deadline, dan Hapus sesuai hak akses
* [ ] Modal Atur Status menyimpan status dan catatan
* [ ] Modal Atur Deadline menyimpan tanggal deadline
* [ ] Modal Hapus menampilkan konfirmasi sebelum submit
* [ ] CSRF tetap ada pada semua form aksi
* [ ] Tampilan desktop rapi
* [ ] Tampilan mobile rapi
* [ ] Tidak ada fatal error
## 2026-08-03 09:07:06 - Pisahkan nama kegiatan dan template CHR jadwal reviu

### File Diubah

* review.php
* chr_helpers.php
* db/schema_bootstrap.php
* deploy/migrations/20260803_090111_review_template_mapping.sql
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Memisahkan Nama/Judul Kegiatan Reviu dari Jenis Reviu pada form Buat Jadwal.
* Mengubah form Buat Jadwal Reviu menjadi modal mengambang.
* Menyimpan template_code dan template_version berdasarkan jenis/template yang dipilih.
* Menambahkan pemetaan template pada Master Jenis Reviu.
* Memperbaiki resolver CHR agar mengutamakan template yang tersimpan pada jadwal.

### Dampak

* Jadwal baru tidak lagi memakai nama jenis sebagai judul kegiatan.
* CHR baru memakai template yang dipilih pada jadwal, bukan fallback default.
* Data lama tetap dipertahankan dan dapat dipetakan dari tab Master.
* Tidak mengubah workflow tanda tangan, data_json CHR, role, sidebar, atau database secara langsung.

### Kebutuhan Database

Perlu menjalankan migration:

deploy/migrations/20260803_090111_review_template_mapping.sql

### Checklist Pengujian

* [ ] Modal Buat Jadwal Reviu terbuka
* [ ] Nama kegiatan wajib diisi
* [ ] Jenis reviu wajib dipilih
* [ ] Unit kerja wajib dipilih
* [ ] Tanggal mulai tidak melebihi tanggal selesai
* [ ] Jenis tanpa template ditolak saat simpan
* [ ] Jadwal baru menyimpan template_code dan template_version
* [ ] CHR memakai template sesuai jadwal
* [ ] Master Jenis Reviu menampilkan status pemetaan
* [ ] Pemetaan template jenis lama dapat disimpan
* [ ] Data lama tetap tampil
* [ ] Tidak ada fatal error

## 2026-08-03 08:21:59 - Tambahkan opsi lihat isi lengkap pelaporan

### File Diubah

* pelaporan.php
* includes/report_detail_modal.php
* assets/js/report_detail_modal.js
* public/assets/js/report_detail_modal.js
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Menambahkan tombol Lihat Isi Lengkap pada kolom isi ringkas tabel pelaporan.
* Menggunakan modal detail laporan yang sudah ada untuk membaca isi laporan penuh.
* Menambahkan fokus modal ke panel Isi Laporan tanpa mengubah tombol Riwayat.

### Dampak

* Pengguna dapat membaca isi pengaduan lengkap tanpa pindah halaman.
* Tombol Riwayat tetap fokus ke bagian Riwayat Proses.
* Tidak mengubah status, disposisi, monitoring tindak lanjut, dashboard, sidebar, atau database.

### Kebutuhan Database

Tidak ada perubahan database

### Checklist Pengujian

* [ ] Tombol Lihat Isi Lengkap tampil di setiap baris pelaporan
* [ ] Klik tombol membuka modal detail laporan
* [ ] Modal fokus ke bagian Isi Laporan
* [ ] Isi laporan panjang tampil utuh dan tidak meluber
* [ ] Tombol Riwayat tetap fokus ke Riwayat Proses
* [ ] Tombol aksi status tetap normal
* [ ] Tampilan desktop rapi
* [ ] Tampilan mobile rapi
* [ ] Tidak ada fatal error

## 2026-08-03 08:08:53 - Perbaikan popup detail laporan dan riwayat pengaduan

### File Diubah

* dashboard.php
* pelaporan.php
* report_detail.php
* public/report_detail.php
* includes/report_detail_modal.php
* includes/url_helpers.php
* assets/js/report_detail_modal.js
* public/assets/js/report_detail_modal.js
* assets/css/ui_base.css
* public/assets/css/ui_base.css
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Menambahkan modal reusable untuk detail laporan dan riwayat pengaduan.
* Mengubah tombol Lihat Detail dashboard agar membuka popup tanpa pindah halaman.
* Mengubah tombol Riwayat dan tombol lampiran di halaman pelaporan agar membuka popup detail.
* Menambahkan endpoint JSON aman untuk mengambil detail laporan, lampiran, riwayat, dan tindak lanjut.
* Menambahkan JavaScript vanilla dan CSS modal responsif.

### Dampak

* Detail laporan dapat dibaca dari dashboard tanpa reload.
* Riwayat pengaduan dapat dibuka dari halaman pelaporan.
* Tidak mengubah alur status, disposisi, tindak lanjut, login, role, atau database.

### Kebutuhan Database

Tidak ada perubahan database

### Checklist Pengujian

* [ ] Dashboard terbuka normal
* [ ] Tombol Lihat Detail membuka modal
* [ ] Tombol Riwayat membuka modal dan fokus ke riwayat
* [ ] Isi laporan tampil lengkap dan aman
* [ ] Lampiran tampil melalui endpoint download
* [ ] Modal bisa ditutup dengan X, Tutup, backdrop, dan Escape
* [ ] Halaman pelaporan tetap bisa menjalankan aksi status
* [ ] Tidak ada fatal error
* [ ] Tidak ada file sensitif ikut berubah

## 2026-06-25 13:05:00 - Rapikan posisi media carousel dan indikator slide

### File Diubah

* login.php
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Membuat media portrait/9:16 tetap berada di tengah frame carousel
* Merapikan wrapper media clickable/lightbox
* Menambahkan indikator slide modern berbentuk bulatan unik
* Menyinkronkan jumlah indikator dengan jumlah media publik
* Mempertahankan swift otomatis dan lightbox media

### Dampak

* Tampilan carousel media publik lebih rapi
* Media portrait tidak lagi menempel ke kiri
* Pengguna dapat melihat jumlah media melalui indikator slide
* Navigasi media lebih modern

### Kebutuhan Database

Tidak ada perubahan database

### Checklist Pengujian

* [ ] Gambar portrait 9:16 tampil center di frame
* [ ] Gambar landscape tampil rapi
* [ ] Video portrait tampil center
* [ ] Video landscape tampil rapi
* [ ] Klik gambar membuka popup
* [ ] Klik video membuka popup
* [ ] Indikator bulatan muncul sesuai jumlah media
* [ ] Indikator aktif berubah sesuai slide
* [ ] Klik indikator berpindah ke slide yang benar
* [ ] Jika hanya 1 media, indikator tidak mengganggu
* [ ] Swift otomatis tetap berjalan sesuai pengaturan
* [ ] Tampilan desktop rapi
* [ ] Tampilan mobile rapi

## 2026-06-25 12:55:00 - Rapikan rasio popup media publik

### File Diubah

* login.php
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Membuat popup media mengikuti rasio gambar/video
* Menambahkan class portrait, landscape, dan square pada lightbox
* Mengurangi area kosong hitam pada video portrait
* Mempertahankan object-fit contain agar media tidak terpotong

### Dampak

* Tampilan popup gambar/video lebih rapi
* Video portrait tampil lebih proporsional
* Media tetap responsif di desktop dan mobile

### Kebutuhan Database

Tidak ada perubahan database

### Checklist Pengujian

* [ ] Popup gambar landscape tampil lebar proporsional
* [ ] Popup gambar portrait tampil ramping proporsional
* [ ] Popup video landscape tampil lebar proporsional
* [ ] Popup video portrait tampil ramping dan tidak terlalu banyak area hitam
* [ ] Popup mobile tetap rapi
* [ ] Tombol close tetap berfungsi
* [ ] Escape tetap menutup popup
* [ ] Video berhenti saat popup ditutup
* [ ] Carousel tetap berjalan sesuai pengaturan swift otomatis

## 2026-06-25 12:45:00 - Tambah popup media edukasi publik

### File Diubah

* login.php
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Menambahkan lightbox/popup untuk gambar dan video media publik
* Membuat media carousel hanya sebagai preview
* Menambahkan ikon play pada preview video
* Menjaga carousel tetap mengikuti pengaturan swift otomatis
* Menghentikan video saat popup ditutup

### Dampak

* Media publik lebih modern dan nyaman dilihat
* Video tidak lagi mengganggu swift otomatis carousel
* Pengguna dapat melihat gambar/video dalam ukuran besar

### Kebutuhan Database

Tidak ada perubahan database

### Checklist Pengujian

* [ ] Klik gambar membuka popup gambar
* [ ] Klik video membuka popup video
* [ ] Video di popup bisa diputar
* [ ] Video berhenti saat popup ditutup
* [ ] Escape menutup popup
* [ ] Klik backdrop menutup popup
* [ ] Carousel tetap swift otomatis jika auto_slide aktif
* [ ] Carousel tidak swift otomatis jika auto_slide nonaktif
* [ ] Durasi swift mengikuti pengaturan admin
* [ ] Tampilan desktop rapi
* [ ] Tampilan mobile rapi
* [ ] Tidak ada error JavaScript
* [ ] Tidak ada error PHP

## 2026-06-25 11:34:46 - Tambah pengaturan swift otomatis media publik

### File Diubah

* public_media.php
* login.php
* deploy/migrations/20260625_113433_add_public_media_auto_slide.sql
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Menambahkan kolom auto_slide dan slide_interval pada public_media
* Menambahkan pengaturan Swift Otomatis di Kelola Media Publik
* Menambahkan pengaturan durasi swift per media
* Menyesuaikan carousel publik agar mengikuti pengaturan per media
* Mempertahankan pause carousel saat video sedang diputar

### Dampak

* Admin dapat mengatur media mana yang berpindah otomatis
* Video tidak lagi membuat carousel berhenti permanen
* Halaman publik lebih fleksibel dan mudah dikendalikan

### Kebutuhan Database

* Jalankan migration baru untuk menambah kolom auto_slide dan slide_interval

### Checklist Pengujian

* [ ] Upload gambar dengan swift otomatis aktif
* [ ] Upload video dengan swift otomatis aktif
* [ ] Upload video dengan thumbnail
* [ ] Set durasi swift 5 detik
* [ ] Set durasi swift 10 detik
* [ ] Nonaktifkan swift otomatis pada salah satu media
* [ ] Media dengan swift nonaktif tidak berpindah otomatis
* [ ] Media dengan swift aktif berpindah otomatis sesuai durasi
* [ ] Video yang sedang play tidak berpindah
* [ ] Video pause/ended membuat carousel bisa lanjut
* [ ] Edit media tetap berhasil
* [ ] Hapus media tetap berhasil
* [ ] Halaman publik tidak error
* [ ] Kelola Media Publik tidak error

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

## 2026-07-10 11:13:51 - Pondasi mesin template CHR dinamis

### File Baru

* chr_templates.php
* deploy/migrations/20260710_add_chr_template_metadata.sql

### File Diubah

* chr_helpers.php
* .gitignore
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Menambahkan registry template CHR.
* Menambahkan mapping jenis reviu ke template.
* Menambahkan metadata template_code dan template_version.
* Menjaga kompatibilitas CHR lama.
* Menambahkan fallback template legacy.
* Belum mengubah tampilan form CHR.
* Belum mengubah ekspor Word/PDF.
* Menyesuaikan .gitignore agar migration deploy/migrations dapat dicommit tanpa menyertakan credential lokal atau file backup.

### Dampak

* Sistem mulai dapat mengenali jenis template CHR.
* CHR lama tetap menggunakan form legacy.
* Template baru dapat ditambahkan bertahap.
* Tidak ada perubahan visual pada tahap ini.

### Kebutuhan Database

Jalankan:
`deploy/migrations/20260710_add_chr_template_metadata.sql`

### Checklist Pengujian

* [x] php -l chr_helpers.php sukses
* [x] php -l chr_templates.php sukses
* [ ] migration dapat dijalankan
* [ ] CHR lama tetap terbuka
* [ ] CHR lama tetap dapat disimpan
* [ ] data_json lama tetap kompatibel
* [x] resolver reviu anggaran = chr_rkakl
* [x] resolver LHKPN/LHKASN = chr_lhkpn_lhkasn
* [x] resolver Manajemen Resiko = chr_manajemen_risiko
* [x] SPIPT fallback legacy
* [x] Kehadiran Pegawai fallback legacy
* [x] tidak ada perubahan visual
* [x] tidak ada perubahan ekspor
* [x] tidak ada parse error

## 2026-07-10 11:36:23 - Perbaikan penyimpanan CHR legacy

### File Diubah

* chr_helpers.php
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Memperbaiki penyimpanan CHR lama agar tidak menimpa seluruh data_json.
* Menambahkan merge data lama dan input baru.
* Mempertahankan field yang tidak dikirim form.
* Menjaga repeater dan struktur nested legacy.
* Menjaga metadata template.

### Dampak

* CHR lama aman disimpan ulang.
* Data lama tidak hilang.
* Template legacy tetap kompatibel.
* Tidak ada perubahan visual.
* Tidak ada perubahan ekspor.

### Kebutuhan Database

Tidak ada perubahan database tambahan.

### Checklist Pengujian

* [x] php -l chr_helpers.php sukses
* [ ] CHR lama dapat dibuka
* [ ] CHR lama dapat disimpan
* [ ] panjang data_json tidak turun drastis
* [x] field lama tetap ada pada simulasi merge
* [x] perubahan satu field tersimpan pada simulasi merge
* [ ] repeater tetap utuh
* [ ] tanda tangan tetap utuh
* [ ] template_code legacy tersimpan
* [ ] ekspor tidak berubah
* [x] tidak ada parse error

## 2026-07-10 11:58:17 - Renderer dinamis dan template CHR SOP

### File Baru

* chr_form_renderer.php
* chr_template_sop.php
* deploy/migrations/20260710_add_jenis_reviu_sop.sql

### File Diubah

* chr_templates.php
* chr_helpers.php
* review.php
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Menambahkan renderer form CHR dinamis.
* Menambahkan template CHR SOP.
* Menambahkan repeater daftar SOP, temuan, tindak lanjut, dan rekomendasi.
* Menambahkan normalisasi input dinamis.
* Mengaktifkan renderer dinamis hanya untuk CHR SOP.
* Mempertahankan renderer legacy untuk CHR lama.
* Menonaktifkan ekspor legacy pada CHR SOP.

### Dampak

* CHR SOP dapat diisi melalui form dinamis.
* CHR SOP dapat disimpan dan dibuka kembali.
* CHR lama tetap kompatibel.
* Belum ada ekspor Word/PDF khusus CHR SOP.

### Kebutuhan Database

* Metadata template menggunakan migration Tahap 1.
* Untuk pengujian jenis reviu SOP, jalankan:
  `deploy/migrations/20260710_add_jenis_reviu_sop.sql`

### Checklist Pengujian

* [x] php -l seluruh file berhasil
* [ ] CHR legacy tetap normal
* [ ] data_json legacy tetap utuh
* [x] resolver SOP menghasilkan chr_sop
* [x] form dinamis SOP tampil pada simulasi render
* [ ] form legacy tidak tampil bersama form SOP
* [x] identitas tersedia pada struktur dinamis
* [x] daftar SOP tersimpan pada normalisasi simulasi
* [x] hasil temuan tersimpan pada normalisasi simulasi
* [x] tindak lanjut tersedia pada struktur dinamis
* [x] rekomendasi tersimpan pada normalisasi simulasi
* [x] repeater tambah/hapus tersedia pada renderer
* [ ] data dapat dibuka kembali
* [x] teks merah tidak muncul pada template/renderer
* [x] ekspor legacy tidak digunakan untuk SOP
* [ ] tidak ada error JavaScript
* [x] tidak ada parse error

## 2026-07-10 16:57:23 - Rapikan blok tanda tangan CHR SOP

### File Diubah

* chr_form_renderer.php
* chr_helpers.php
* chr_template_sop.php
* review.php
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Merapikan section pengesahan CHR SOP menjadi tiga kolom: Direktur, Ketua Tim, dan Anggota Tim.
* Menampilkan setiap blok tanda tangan dalam card/subpanel seragam.
* Mempertahankan canvas tanda tangan, tombol Bersihkan, tombol Simpan Tanda Tangan, dan pratinjau tersimpan.
* Mengubah anggota tim menjadi sub-card repeater yang tetap mendukung tambah/hapus.
* Menambahkan inisialisasi signature pad untuk anggota yang ditambahkan secara dinamis.
* Merapikan ukuran canvas, border, tombol, preview, dan responsivitas.

### Dampak

* Blok tanda tangan CHR SOP tampil lebih formal, rapi, dan konsisten.
* Mekanisme tanda tangan tetap memakai controller existing di review.php.
* Tidak ada perubahan database.
* Tidak mengubah backend penyimpanan CHR.

### Kebutuhan Database

Tidak ada perubahan database

### Checklist Pengujian

* [x] php -l chr_form_renderer.php sukses
* [x] php -l chr_helpers.php sukses
* [x] php -l chr_template_sop.php sukses
* [x] php -l review.php sukses
* [x] Renderer CHR SOP menghasilkan elemen tanda tangan
* [x] Data tanda tangan canvas tidak terpotong sanitizer
* [ ] Tanda tangan bisa digambar di browser
* [ ] Tanda tangan bisa dibersihkan
* [ ] Tanda tangan bisa disimpan
* [ ] Tanda tangan tersimpan bisa dimuat ulang
* [ ] Repeater anggota tambah/hapus normal
* [ ] Layout desktop rapi
* [ ] Layout mobile rapi

## 2026-07-10 17:30:41 - Profil pegawai dan picker penanda tangan CHR SOP

### File Diubah

* pengguna.php
* db/schema_bootstrap.php
* chr_helpers.php
* chr_form_renderer.php
* chr_template_sop.php
* review.php
* deploy/migrations/20260710_add_pengguna_employee_profile.sql
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Menambahkan migration profil pegawai pada tabel pengguna: NIP, jabatan, dan unit kerja.
* Menambahkan fallback schema bootstrap untuk kolom profil pegawai tanpa membuat FK otomatis.
* Memperbaiki bug query INSERT pengguna dan bind_param yang tidak sesuai.
* Menambahkan field profil pegawai pada Master Pengguna: Nama, NIP, Jabatan, Unit Kerja, Username, Password, Peran, Status, dan hak akses.
* Menambahkan sumber data pegawai aktif dengan profil lengkap untuk picker penanda tangan CHR SOP.
* Mengubah pengesahan CHR SOP agar memakai picker pegawai, snapshot readonly, tanggal, signature pad, dan preview.
* Menambahkan validasi server untuk snapshot pegawai, profil lengkap, pegawai duplikat, signature data URL, dan pergantian penanda tangan.
* Mempertahankan data pengesahan manual lama agar tetap bisa dibuka.

### Dampak

* Admin dapat melengkapi profil pegawai dari Master Pengguna.
* Penanda tangan CHR SOP dipilih dari pegawai aktif, bukan diketik manual.
* Snapshot nama, NIP, jabatan, unit kerja, tanggal, dan tanda tangan tersimpan di data_json dinamis.
* CHR legacy tidak diubah.
* Tidak ada perubahan pada tabel reviu_chr.
* Ekspor Word/PDF SOP tidak dikerjakan.

### Kebutuhan Database

* Migration baru: deploy/migrations/20260710_add_pengguna_employee_profile.sql
* Migration sudah dijalankan lokal pada database ski_db.

### Checklist Pengujian

* [x] Audit pengguna.php selesai
* [x] Audit signature pad selesai
* [x] Audit struktur pengguna dan unit_kerja selesai
* [x] Backup file sebelum patch dibuat
* [x] Migration lokal berhasil dijalankan
* [x] Kolom nip, jabatan, unit_id tersedia di pengguna
* [x] FK pengguna.unit_id ke unit_kerja.id tersedia
* [x] php -l pengguna.php sukses
* [x] php -l db/schema_bootstrap.php sukses
* [x] php -l chr_helpers.php sukses
* [x] php -l chr_form_renderer.php sukses
* [x] php -l chr_template_sop.php sukses
* [x] php -l review.php sukses
* [x] php -l chr_templates.php sukses
* [x] Picker pegawai dapat mengambil daftar pengguna aktif
* [ ] Tambah pengguna dengan profil lengkap diuji manual
* [ ] Edit pengguna diuji manual
* [ ] Login pengguna lama dengan profil kosong diuji manual
* [ ] Dropdown Unit Kerja diuji manual
* [ ] CHR SOP reviu_id 30 diuji manual
* [ ] Pilih Pejabat Menyetujui diuji manual
* [ ] Pilih Ketua Tim diuji manual
* [ ] Tambah lebih dari satu Anggota Tim diuji manual
* [ ] Signature pad tiap orang diuji manual
* [ ] Simpan dan buka ulang CHR SOP diuji manual
* [ ] Data pengesahan manual lama tetap terbaca diuji manual
* [ ] CHR legacy tetap normal diuji manual

## 2026-07-10 17:40:08 - Koreksi konsep pengesahan CHR SOP per akun

### File Diubah

* chr_helpers.php
* chr_form_renderer.php
* review.php
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Memisahkan posisi dokumen dari role aplikasi melalui document_role dan document_role_label.
* Menambahkan status_signature, signed_at, signed_ip, dan signed_user_agent pada struktur signer CHR SOP.
* Membuat signature pad hanya aktif untuk akun penanda tangan yang sesuai dan masih berstatus menunggu.
* Menambahkan validasi server agar tanda tangan hanya diterima jika user login sama dengan user_id penanda tangan.
* Menambahkan daftar Dokumen Menunggu Tanda Tangan pada halaman review untuk akun penanda tangan.
* Mengubah validasi duplikasi agar pegawai yang sama ditolak pada posisi yang sama, sementara posisi berbeda tetap menjadi record terpisah.

### Dampak

* Penyusun CHR SOP hanya memilih penanda tangan, tidak dapat menandatangani atas nama penanda tangan lain.
* Label pengesahan menggunakan posisi dokumen seperti Pejabat Menyetujui, Ketua Tim, dan Anggota Tim, bukan role aplikasi.
* Jabatan resmi tetap berasal dari profil pegawai.
* Tidak ada perubahan database baru.
* Tidak mengubah CHR legacy dan tidak mengerjakan ekspor CHR SOP.

### Kebutuhan Database

Tidak ada migration baru. Tetap memakai migration: deploy/migrations/20260710_add_pengguna_employee_profile.sql

### Checklist Pengujian

* [x] php -l chr_helpers.php sukses
* [x] php -l chr_form_renderer.php sukses
* [x] php -l review.php sukses
* [x] php -l pengguna.php sukses
* [x] php -l db/schema_bootstrap.php sukses
* [x] php -l chr_template_sop.php sukses
* [x] Renderer menonaktifkan signature pad untuk akun non-penanda tangan
* [x] Renderer mengaktifkan signature pad untuk akun pemilik tanda tangan yang masih waiting
* [x] git diff --check sukses
* [ ] Server ACL tanda tangan diuji dengan profil pegawai lengkap
* [ ] Akun Direktur/auditee menampilkan posisi Pejabat Menyetujui, bukan Auditee
* [ ] Akun Kepala SKI/auditor menampilkan posisi Ketua Tim, bukan role aplikasi
* [ ] Anggota Tim hanya dapat menandatangani bagian miliknya
* [ ] signed_at tersimpan saat akun pemilik menandatangani
* [ ] Daftar Dokumen Menunggu Tanda Tangan tampil untuk akun terkait
* [ ] CHR SOP reviu_id 30 tetap dapat dibuka
* [ ] CHR legacy tetap normal

## 2026-07-10 18:02:17 - Workflow pengesahan CHR SOP

### File Diubah

* chr_helpers.php
* chr_form_renderer.php
* review.php
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Menambahkan workflow pengesahan CHR SOP dengan status draft, waiting_signatures, partially_signed, approved, dan returned.
* Menambahkan tombol Ajukan Pengesahan, Kembalikan untuk Perbaikan, Buka untuk Perbaikan, dan Simpan Tanda Tangan sesuai status workflow.
* Mengunci isi CHR SOP dan daftar penanda tangan setelah dokumen diajukan.
* Membatasi perubahan isi melalui POST manual saat workflow bukan draft.
* Menambahkan reset tanda tangan saat CHR SOP dibuka kembali untuk perbaikan.
* Menghitung status partially_signed dan approved berdasarkan status signer aktual.
* Memfilter daftar Dokumen Menunggu Tanda Tangan hanya untuk workflow waiting_signatures atau partially_signed.

### Dampak

* CHR SOP memiliki alur pengesahan minimum yang lebih aman.
* Penyusun hanya dapat mengubah isi dan penanda tangan saat status draft.
* Signer dapat menandatangani atau mengembalikan dokumen sesuai akun masing-masing.
* CHR legacy tidak diubah.
* Ekspor CHR SOP tetap belum aktif.

### Kebutuhan Database

Tidak ada perubahan database baru.

### Checklist Pengujian

* [x] php -l chr_helpers.php sukses
* [x] php -l chr_form_renderer.php sukses
* [x] php -l review.php sukses
* [x] php -l pengguna.php sukses
* [x] php -l db/schema_bootstrap.php sukses
* [x] php -l chr_template_sop.php sukses
* [x] Perhitungan partially_signed berhasil pada simulasi CLI
* [x] Perhitungan approved berhasil pada simulasi CLI
* [x] Renderer draft menonaktifkan signature pad
* [x] Renderer waiting_signatures mengaktifkan signature pad hanya untuk pemilik akun
* [x] Renderer waiting_signatures mengunci picker penanda tangan
* [x] git diff --check sukses
* [ ] Ajukan Pengesahan diuji manual di browser
* [ ] Dokumen terkunci setelah diajukan diuji manual
* [ ] Signer nonpemilik ditolak saat tanda tangan diuji manual
* [ ] Tanda tangan sebagian diuji manual
* [ ] Seluruh signer selesai dan status approved diuji manual
* [ ] Kembalikan untuk Perbaikan dengan catatan diuji manual
* [ ] Buka untuk Perbaikan dan reset signature diuji manual
* [ ] Ajukan ulang diuji manual
* [ ] CHR SOP reviu_id 30 diuji manual
* [ ] CHR legacy tetap normal diuji manual

## 2026-07-12 10:46:15 - Rapikan UI Pengesahan CHR SOP

### File Diubah

* chr_form_renderer.php
* review.php
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Merapikan layout pengesahan CHR SOP menjadi dua card utama untuk Direktur/Pejabat Menyetujui dan Ketua Tim, serta panel Anggota Tim penuh dengan grid responsif.
* Mengubah picker penanda tangan menjadi komponen pencarian tunggal dengan hasil berisi nama, NIP, jabatan resmi, dan unit kerja.
* Mengganti input identitas readonly dengan ringkasan profil penanda tangan yang lebih rapi.
* Menyesuaikan tampilan tanda tangan berdasarkan status workflow: draft, menunggu tanda tangan, ditandatangani, dikembalikan, dan disahkan.
* Menyembunyikan input tanggal/lokasi dari UI pengesahan dan mempertahankannya sebagai hidden field untuk kompatibilitas data lama.
* Menambahkan ringkasan kesiapan pengajuan dekat tombol Ajukan Pengesahan.

### Dampak

* UI pengesahan CHR SOP lebih formal, ringkas, dan mudah dipahami.
* Mekanisme tanda tangan, key data, struktur JSON, dan backend penyimpanan tidak diubah.
* Tanggal tanda tangan tetap mengikuti signed_at otomatis.
* Tidak ada perubahan database.

### Kebutuhan Database

Tidak ada perubahan database

### Checklist Pengujian

* [x] php -l chr_form_renderer.php sukses
* [x] php -l review.php sukses
* [ ] Picker pegawai dapat mencari nama, NIP, jabatan, atau unit kerja
* [ ] Draft menampilkan picker dan profil tanpa canvas tanda tangan
* [ ] Waiting/partial menampilkan canvas hanya untuk pemilik tanda tangan
* [ ] Signed/approved menampilkan preview tanda tangan tanpa canvas baru
* [ ] Returned menampilkan status dikembalikan tanpa canvas tanda tangan
* [ ] Repeater anggota tetap bisa tambah/hapus
* [ ] Tombol Bersihkan dan Tandatangani tetap bekerja
* [ ] Tampilan desktop rapi
* [ ] Tampilan mobile rapi

## 2026-07-12 10:52:20 - Search-first picker pegawai pengesahan CHR SOP

### File Diubah

* chr_form_renderer.php
* review.php
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Mengubah picker pegawai pengesahan CHR SOP menjadi pola search-first/autocomplete.
* Menyembunyikan daftar pegawai pada kondisi awal dan menampilkan bantuan ketik minimal 2 karakter.
* Membatasi hasil pencarian pegawai maksimal 10 item.
* Menampilkan hasil pencarian berisi nama, jabatan resmi, unit kerja, dan NIP ringkas.
* Mempertahankan kartu ringkasan pegawai setelah dipilih serta tombol Ganti Pegawai pada status draft.
* Menandai pegawai dengan profil belum lengkap sebagai pilihan disabled.

### Dampak

* Pemilihan Pejabat Menyetujui, Ketua Tim, dan Anggota Tim lebih ringkas dan tidak menampilkan seluruh daftar pegawai secara default.
* Workflow, validasi server, mekanisme tanda tangan, dan struktur data tidak diubah.
* Tidak ada perubahan database.

### Kebutuhan Database

Tidak ada perubahan database

### Checklist Pengujian

* [x] php -l chr_form_renderer.php sukses
* [x] php -l review.php sukses
* [ ] Kondisi awal picker hanya menampilkan input pencarian dan bantuan
* [ ] Hasil pencarian muncul setelah minimal 2 karakter
* [ ] Hasil pencarian maksimal 10 item
* [ ] Pegawai profil belum lengkap tampil disabled
* [ ] Setelah pegawai dipilih, panel hasil tertutup dan kartu ringkasan tampil
* [ ] Tombol Ganti Pegawai tampil pada status draft
* [ ] Pejabat Menyetujui, Ketua Tim, dan Anggota Tim memakai perilaku yang sama

## 2026-07-12 11:07:27 - Sinkronisasi kesiapan pengajuan CHR SOP

### File Diubah

* chr_form_renderer.php
* review.php
* chr_helpers.php
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Menambahkan penghitung kesiapan pengajuan client-side yang membaca hidden input signer aktual.
* Memperbarui panel Kesiapan Pengajuan setelah pegawai dipilih, diganti, anggota ditambah, atau anggota dihapus.
* Mengaktifkan/menonaktifkan tombol Ajukan Pengesahan berdasarkan user_id, profil lengkap, Pejabat Menyetujui, Ketua Tim, dan minimal satu Anggota Tim.
* Menambahkan data hook pada panel kesiapan dan tombol Ajukan Pengesahan.
* Memperkuat validasi server agar profil signer wajib lengkap sebelum pengesahan diajukan.
* Memperbaiki pesan error server saat profil signer tidak valid.

### Dampak

* Panel Kesiapan Pengajuan langsung sinkron dengan pilihan pegawai di UI draft.
* Tombol Ajukan Pengesahan aktif setelah minimal 3 signer valid dan lengkap dipilih.
* Server tetap menjadi sumber validasi utama sebelum workflow berubah ke waiting_signatures.
* Tidak ada perubahan database.

### Kebutuhan Database

Tidak ada perubahan database

### Checklist Pengujian

* [x] php -l chr_form_renderer.php sukses
* [x] php -l review.php sukses
* [x] php -l chr_helpers.php sukses
* [x] Uji server readiness 3 signer lengkap sukses
* [x] Render draft memuat hook readiness client-side
* [ ] Pilih Pejabat Menyetujui menaikkan jumlah signer di browser
* [ ] Pilih Ketua Tim menaikkan jumlah signer di browser
* [ ] Pilih Anggota Tim mengaktifkan tombol Ajukan jika semua lengkap
* [ ] Hapus Anggota Tim menonaktifkan tombol Ajukan di browser
* [ ] Simpan Draft dan reload mempertahankan readiness benar
* [ ] Ajukan Pengesahan mengubah status menjadi waiting_signatures

## 2026-07-12 11:24:00 - Aktifkan ekspor pratinjau CHR SOP

### File Diubah

* review.php
* chr_sop_export_common.php
* chr_sop_export_render.php
* chr_sop_export.php
* chr_sop_export_pdf.php
* public/chr_sop_export.php
* public/chr_sop_export_pdf.php
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Menambahkan endpoint ekspor khusus CHR SOP yang terpisah dari ekspor CHR legacy.
* Menambahkan tombol Pratinjau Dokumen, Unduh Word Pratinjau, dan Unduh PDF Pratinjau untuk CHR SOP.
* Menambahkan tombol Word/PDF Final hanya saat workflow approved dan seluruh signer siap secara server-side.
* Membuat renderer dokumen SOP berdasarkan urutan section template chr_sop dan data_json dynamic.
* Menampilkan watermark DRAFT - BELUM DISAHKAN pada pratinjau/non-final.
* Merender pengesahan dengan snapshot nama, NIP, jabatan resmi, unit, signature, dan signed_at.
* Membuat Word .docx khusus CHR SOP berbasis OpenXML/ZipArchive tanpa menambah dependency baru.
* Menjaga PDF sebagai HTML print khusus SOP sesuai mekanisme PDF yang sudah ada di aplikasi.

### Dampak

* CHR SOP dapat dipratinjau pada status draft, waiting_signatures, partially_signed, returned, dan approved.
* Final export tetap ditolak jika belum approved atau masih ada signer belum signed.
* Ekspor CHR legacy tidak diubah.
* Ekspor tidak mengubah data, workflow, atau tanda tangan.

### Kebutuhan Database

Tidak ada perubahan database

### Checklist Pengujian

* [x] php -l chr_sop_export_common.php sukses
* [x] php -l chr_sop_export_render.php sukses
* [x] php -l chr_sop_export.php sukses
* [x] php -l chr_sop_export_pdf.php sukses
* [x] php -l public/chr_sop_export.php sukses
* [x] php -l public/chr_sop_export_pdf.php sukses
* [x] php -l review.php sukses
* [x] Uji load reviu_id 30 sukses
* [x] Uji render HTML pratinjau reviu_id 30 sukses
* [x] Uji generate DOCX pratinjau reviu_id 30 sukses
* [x] Uji struktur ZIP DOCX sukses
* [x] Final export reviu_id 30 terblokir saat status belum approved
* [x] git diff --check sukses
* [ ] Pratinjau dibuka manual di browser
* [ ] Word pratinjau dibuka manual di Microsoft Word
* [ ] PDF/print preview dicek manual dari browser
* [ ] Final export diuji manual setelah status approved

## 2026-07-12 11:38:00 - Penyempurnaan format ekspor CHR SOP

### File Diubah

* chr_sop_export_common.php
* chr_sop_export_render.php
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Mengubah header ekspor CHR SOP menjadi format dokumen resmi tanpa label teknis form.
* Mengganti status workflow internal menjadi label bahasa Indonesia.
* Menambahkan helper tanggal Indonesia untuk tampilan ekspor.
* Mengubah field kosong menjadi tanda "-" dan tabel kosong menjadi "Tidak terdapat data".
* Menyelaraskan penomoran menjadi 14 bagian resmi CHR SOP.
* Mengubah Hasil Temuan dari tabel lebar menjadi blok vertikal per temuan.
* Merapikan bagian Pengesahan agar menyerupai blok tanda tangan dokumen resmi.
* Mencegah role aplikasi/unit generik tampil sebagai jabatan utama tanda tangan.
* Menjaga watermark draft tetap ada pada dokumen non-final.
* Menyelaraskan isi dan urutan HTML/PDF print preview dan Word DOCX.

### Dampak

* Ekspor CHR SOP terlihat lebih formal dan lebih mudah dibaca.
* Data, workflow, form, dan struktur data_json tidak diubah.
* Ekspor CHR legacy tidak diubah.
* Tidak ada perubahan database.

### Kebutuhan Database

Tidak ada perubahan database

### Checklist Pengujian

* [x] php -l chr_sop_export_common.php sukses
* [x] php -l chr_sop_export_render.php sukses
* [x] Uji HTML reviu_id 30 memuat watermark draft
* [x] Uji HTML reviu_id 30 tidak memuat waiting_signatures
* [x] Uji HTML reviu_id 30 tidak memuat label teknis header
* [x] Uji HTML reviu_id 30 tidak memuat "Belum ada data"
* [x] Uji HTML reviu_id 30 merender Hasil Temuan sebagai blok
* [x] Uji DOCX reviu_id 30 valid sebagai ZIP DOCX
* [x] Uji DOCX reviu_id 30 tidak memuat waiting_signatures
* [x] Uji DOCX reviu_id 30 tidak memuat "Belum ada data"
* [ ] PDF/print preview dicek manual di browser
* [ ] Word DOCX dicek manual di Microsoft Word
* [ ] Final approved dicek manual tanpa watermark

## 2026-07-12 11:50:00 - Audit dan guard legacy CHR RKAKL

### File Diubah

* chr_helpers.php
* review.php
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Mengaudit struktur CHR RKAKL yang saat ini masih memakai renderer legacy dan blok tanda tangan manual lama.
* Menambahkan helper deteksi signature legacy untuk direktur, ketua tim, anggota tim, dan signature manual lama.
* Menambahkan helper deteksi mode pengesahan RKAKL dengan dukungan data lama yang masih tersimpan sebagai template_code legacy tetapi jenis reviu terselesaikan sebagai chr_rkakl.
* Menambahkan peringatan UI pada RKAKL legacy bahwa data tanda tangan manual lama dipertahankan dan tidak dikonversi otomatis ke workflow baru.
* Tidak melakukan migrasi massal data_json dan tidak menimpa signature RKAKL lama.

### Dampak

* RKAKL legacy lebih aman dibuka karena mode legacy dikenali eksplisit.
* Dokumen RKAKL lama dengan signature manual tetap kompatibel.
* CHR SOP tidak diubah perilakunya.
* Belum mengaktifkan workflow pengesahan baru untuk RKAKL karena perlu template RKAKL dinamis dan inisialisasi eksplisit agar tidak merusak data lama.
* Tidak ada perubahan database.

### Kebutuhan Database

Tidak ada perubahan database

### Checklist Pengujian

* [x] php -l chr_helpers.php sukses
* [x] php -l review.php sukses
* [x] Uji reviu_id 22 resolved sebagai chr_rkakl
* [x] Uji reviu_id 22 terdeteksi mode legacy
* [x] Uji reviu_id 22 signature legacy terdeteksi
* [ ] RKAKL legacy dibuka manual di browser
* [ ] Signature lama RKAKL dicek manual tetap tampil
* [ ] Simpan ulang RKAKL legacy dicek manual tidak menghapus data
* [ ] Workflow RKAKL baru belum diaktifkan sampai ada template/inisialisasi eksplisit

## 2026-07-12 12:12:05 - Aktifkan fondasi pengesahan standar CHR RKAKL

### File Diubah

* chr_template_rkakl.php
* chr_templates.php
* chr_helpers.php
* review.php
* chr_sop_export_common.php
* chr_sop_export_render.php
* chr_sop_export.php
* chr_sop_export_pdf.php
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Menambahkan template dinamis CHR RKAKL dengan section identitas, ruang lingkup, data anggaran, hasil reviu, tindak lanjut, kesimpulan, dan pengesahan.
* Mengaktifkan CHR RKAKL baru agar memakai workflow pengesahan standar seperti CHR SOP.
* Menambahkan deteksi mode legacy RKAKL agar data lama dengan tanda tangan manual tetap memakai form legacy.
* Menggeneralisasi picker pegawai, readiness, workflow, daftar tugas tanda tangan, dan ekspor pratinjau/final untuk template approval standar.
* Menyesuaikan default subjudul RKAKL menjadi REVIU RKA/RKAKL.
* Mempertahankan endpoint ekspor SOP yang sudah ada agar dapat membaca template approval standar tanpa mengubah ekspor legacy.

### Dampak

* RKAKL baru dapat memakai picker pegawai, workflow draft/waiting_signatures/partially_signed/approved/returned, dan tanda tangan per akun.
* RKAKL lama tetap terbuka sebagai legacy dan tidak dikonversi otomatis.
* CHR SOP tetap memakai workflow dan renderer yang sama.
* Tidak ada perubahan database.
* Tidak ada migrasi massal data_json lama.

### Kebutuhan Database

Tidak ada perubahan database

### Checklist Pengujian

* [x] Audit struktur RKAKL legacy selesai
* [x] RKAKL lama reviu_id 22 tetap terbaca sebagai legacy
* [x] RKAKL baru resolve ke template chr_rkakl dynamic
* [x] RKAKL baru memiliki workflow draft
* [x] RKAKL baru menampilkan picker pegawai dan section pengesahan
* [x] Default subjudul RKAKL baru menjadi REVIU RKA/RKAKL
* [x] Renderer ekspor pratinjau RKAKL memuat judul RKAKL dan pengesahan
* [x] Regresi template CHR SOP tetap dynamic
* [x] Regresi form CHR SOP tetap memuat picker pegawai
* [x] php -l file PHP yang diubah sukses
* [ ] Uji browser RKAKL baru memilih Pejabat Menyetujui
* [ ] Uji browser RKAKL baru memilih Ketua Tim
* [ ] Uji browser RKAKL baru memilih Anggota Tim
* [ ] Uji browser Ajukan Pengesahan RKAKL
* [ ] Uji akun signer RKAKL menandatangani bagiannya
* [ ] Uji returned dan buka untuk perbaikan RKAKL
* [ ] Uji ekspor final RKAKL setelah approved
* [ ] Cek console JavaScript di browser

## 2026-07-12 12:17:53 - Koreksi resolver RKAKL ke pengesahan standar

### File Diubah

* chr_helpers.php
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Memperbaiki resolver template agar RKAKL legacy kosong/default dapat memakai renderer dinamis seperti CHR SOP.
* Tetap mempertahankan mode legacy jika RKAKL lama sudah memiliki nama, NIP, jabatan manual, atau tanda tangan manual.
* Menjaga data_json legacy lama tidak dikonversi otomatis jika sudah berisi data pengesahan manual.

### Dampak

* RKAKL yang belum punya pengesahan manual tampil dengan picker pegawai dan workflow standar seperti CHR SOP.
* RKAKL yang sudah punya pengesahan manual tetap aman di mode legacy.
* Tidak ada perubahan database.

### Kebutuhan Database

Tidak ada perubahan database

### Checklist Pengujian

* [x] php -l chr_helpers.php sukses
* [x] RKAKL legacy kosong/default resolve ke chr_rkakl dynamic
* [x] RKAKL legacy berisi nama/NIP manual tetap resolve ke legacy
* [x] RKAKL lama reviu_id 22 yang sudah punya data manual tetap legacy
* [ ] Buka RKAKL kosong/default di browser dan pastikan tampil sama seperti CHR SOP
* [ ] Pilih Pejabat Menyetujui, Ketua Tim, dan Anggota Tim di RKAKL
* [ ] Ajukan Pengesahan RKAKL dari browser
* [ ] Tidak ada error JavaScript

## 2026-07-12 12:24:59 - Paksa RKAKL memakai tampilan pengesahan standar

### File Diubah

* chr_helpers.php
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Mengubah resolver agar semua dokumen jenis RKAKL dibuka dengan template dinamis chr_rkakl.
* Menghentikan fallback tampilan Blok Tanda Tangan legacy untuk RKAKL, termasuk dokumen yang sudah memiliki data manual lama.
* Tetap mempertahankan data dan tanda tangan legacy lama di data_json agar tidak hilang saat dokumen dibuka.

### Dampak

* Tampilan pengesahan RKAKL menjadi sama dengan CHR SOP: picker pegawai, status pengesahan, dan workflow standar.
* Data tanda tangan manual lama tidak dihapus, tetapi tidak lagi menjadi UI utama RKAKL.
* Tidak ada perubahan database.

### Kebutuhan Database

Tidak ada perubahan database

### Checklist Pengujian

* [x] php -l chr_helpers.php sukses
* [x] RKAKL lama dengan stored_code legacy resolve ke chr_rkakl dynamic
* [x] Renderer RKAKL memuat dynamic form
* [x] Renderer RKAKL memuat picker pegawai
* [x] Renderer RKAKL tidak lagi memuat judul Blok Tanda Tangan legacy
* [x] Signature legacy lama tetap ada di data hasil fetch
* [ ] Reload halaman RKAKL di browser
* [ ] Pastikan tampilan pengesahan RKAKL sama dengan CHR SOP
* [ ] Pilih signer RKAKL dan simpan draft
* [ ] Ajukan pengesahan RKAKL

## 2026-07-12 16:11:41 - Ganti penuh template RKAKL berdasarkan format RKA

### File Diubah

* chr_template_rkakl.php
* chr_helpers.php
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Mengganti penuh struktur template chr_rkakl dengan template baru berdasarkan dokumen FORMAT CATATAN HASIL REVIURKA-K/L.
* Menambahkan section identitas dokumen, penyusun, uraian penugasan, data umum, pemeriksaan RKA-K/L, koreksi/rekomendasi/kesimpulan, dan pengesahan.
* Menambahkan tabel/repeater pagu indikatif dan pagu anggaran per sumber dana dan jenis belanja.
* Menambahkan repeater aspek pemeriksaan sesuai struktur dokumen sumber: RKP/Renja-KL, Pagu Anggaran, Alokasi Anggaran, Dokumen Pendukung, Biaya Pemeliharaan, dan Biaya Pengadaan.
* Menjaga pengesahan RKAKL tetap memakai komponen standar CHR SOP: picker pegawai, workflow, signature per akun, dan readiness pengajuan.
* Menonaktifkan tampilan form tanda tangan manual legacy untuk RKAKL melalui resolver dynamic yang sudah ada.

### Dampak

* RKAKL tidak lagi memakai struktur/form lama.
* RKAKL memakai template dinamis baru dengan kode tetap chr_rkakl.
* CHR SOP dan chr_legacy_laporan_keuangan tidak diubah.
* Tidak ada perubahan database.

### Kebutuhan Database

Tidak ada perubahan database

### Checklist Pengujian

* [x] php -l chr_template_rkakl.php sukses
* [x] php -l chr_helpers.php sukses
* [x] RKAKL aktual resolve ke chr_rkakl versi 2
* [x] Form RKAKL memuat section Data Umum
* [x] Form RKAKL memuat section Pemeriksaan RKA-K/L
* [x] Form RKAKL memuat picker pegawai pengesahan
* [x] Form RKAKL tidak memuat Blok Tanda Tangan legacy
* [x] Default aspek pemeriksaan pertama sesuai dokumen sumber
* [x] Ekspor pratinjau RKAKL memuat Pemeriksaan RKA-K/L
* [x] Ekspor pratinjau RKAKL memuat watermark draft
* [ ] Simpan draft RKAKL dari browser
* [ ] Reload draft RKAKL dari browser
* [ ] Ajukan pengesahan RKAKL
* [ ] Tanda tangan RKAKL oleh akun signer
* [ ] Ekspor Word/PDF final setelah approved
* [ ] Cek console JavaScript browser

## 2026-07-12 16:16:37 - Tambahkan pilihan jenis reviu RKAKL

### File Diubah

* chr_templates.php
* db/schema_bootstrap.php
* deploy/migrations/20260712_161900_ensure_jenis_reviu_rkakl.sql
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Menambahkan alias resolver untuk Reviu RKA/RKAKL, Reviu RKA-K/L, Reviu RKAKL, RKA-K/L, dan variasi terkait.
* Menambahkan bootstrap database agar jenis reviu RKAKL tersedia dengan label Reviu RKA/RKAKL.
* Membuat migration untuk mengganti label lama reviu anggaran menjadi Reviu RKA/RKAKL atau menambahkannya jika belum ada.
* Menjalankan migration pada database lokal sehingga dropdown localhost menampilkan Reviu RKA/RKAKL.

### Dampak

* Jenis reviu RKAKL tampil jelas pada dropdown Buat Jadwal Reviu.
* Jadwal baru dengan jenis Reviu RKA/RKAKL otomatis memakai template chr_rkakl dynamic versi 2.
* Jadwal lama yang masih berjenis Reviu Laporan Keuangan tetap memakai legacy laporan keuangan.
* Tidak mengubah CHR SOP dan tidak mengubah tabel reviu_chr.

### Kebutuhan Database

Migration baru: deploy/migrations/20260712_161900_ensure_jenis_reviu_rkakl.sql
Migration sudah dijalankan lokal.

### Checklist Pengujian

* [x] Migration lokal berhasil dijalankan
* [x] Dropdown source jenis_reviu memuat Reviu RKA/RKAKL
* [x] Resolver Reviu RKA/RKAKL mengarah ke chr_rkakl
* [x] Simulasi jadwal baru Reviu RKA/RKAKL memakai renderer dynamic versi 2
* [x] php -l db/schema_bootstrap.php sukses
* [x] php -l chr_templates.php sukses
* [ ] Refresh halaman Jadwal di browser dan cek dropdown
* [ ] Buat jadwal baru dengan jenis Reviu RKA/RKAKL
* [ ] Buka tab CHR jadwal RKAKL baru dan cek template baru tampil

## 2026-07-12 16:31:38 - Template CHR Manajemen Risiko dinamis

### File Diubah

* chr_template_manajemen_risiko.php
* chr_helpers.php
* chr_templates.php
* review.php
* chr_sop_export_common.php
* db/schema_bootstrap.php
* deploy/migrations/20260712_162900_ensure_jenis_reviu_manrisk.sql
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Menambahkan template dinamis chr_manajemen_risiko berdasarkan dokumen FORMAT CHR Manrisk.
* Menambahkan section Identitas Dokumen, Penyusun Dokumen, Komponen Reviu, Hasil Temuan, Tindak Lanjut, Rekomendasi/Kesimpulan, dan Pengesahan.
* Menambahkan repeater Komponen Reviu dan Hasil Temuan per unit sesuai struktur dokumen sumber.
* Mengaktifkan CHR Manajemen Risiko di registry template dan workflow pengesahan standar.
* Menambahkan mapping jenis Reviu Manajemen Risiko ke chr_manajemen_risiko.
* Menambahkan export support melalui renderer export approval standar untuk pratinjau, Word, PDF print preview, dan final setelah approved.
* Menambahkan migration jenis reviu Manajemen Risiko dan menjalankannya lokal.
* Membuat data uji lokal T-MR-260712 untuk validasi template, save/load, dan ekspor preview.

### Dampak

* Jadwal Reviu Manajemen Risiko memakai template dinamis baru, bukan legacy.
* Pengesahan memakai pola CHR SOP/RKAKL: picker pegawai, tanda tangan per akun, workflow draft/waiting/partial/approved/returned.
* CHR SOP, CHR RKAKL, dan CHR legacy tetap dipertahankan.
* Tidak mengubah tabel reviu_chr.

### Kebutuhan Database

Migration baru: deploy/migrations/20260712_162900_ensure_jenis_reviu_manrisk.sql
Migration sudah dijalankan lokal.
Data uji lokal dibuat: kode T-MR-260712.

### Checklist Pengujian

* [x] Ekstraksi struktur dokumen sumber selesai
* [x] Teks contoh nama/NIP dari dokumen sumber tidak dijadikan default template
* [x] Migration lokal berhasil dijalankan
* [x] Jenis Reviu Manajemen Risiko resolve ke chr_manajemen_risiko
* [x] Data uji lokal dibuat
* [x] Template Manrisk tampil dynamic versi 1
* [x] Form legacy tidak tampil pada data uji Manrisk
* [x] Picker pegawai pengesahan tampil
* [x] Save data_json dynamic berhasil
* [x] Reload data_json memuat ulang temuan uji
* [x] Workflow awal draft
* [x] Ekspor preview HTML memuat MANAJEMEN RISIKO dan watermark draft
* [x] Generate Word preview menghasilkan arsip DOCX valid
* [x] Regresi template SOP tetap dynamic
* [x] Regresi template RKAKL tetap dynamic
* [x] Regresi legacy laporan keuangan tetap legacy
* [x] php -l seluruh file PHP yang diubah sukses
* [ ] Uji browser tambah/hapus repeater Manrisk
* [ ] Uji browser pilih signer dan ajukan pengesahan
* [ ] Uji signer menandatangani melalui akun masing-masing
* [ ] Uji returned dan reopen draft
* [ ] Uji Word/PDF final setelah approved
* [ ] Cek console JavaScript browser

## 2026-07-12 16:37:58 - Kosongkan placeholder tanda tangan export

### File Diubah

* chr_sop_export_render.php
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Menghapus teks Menunggu tanda tangan dari area pengesahan pada preview/export dokumen.
* Membiarkan area tanda tangan kosong agar dokumen dapat ditandatangani offline.
* Tetap menampilkan nama, NIP, jabatan/unit, dan tanggal tanda tangan jika signer sudah signed.

### Dampak

* Preview HTML/PDF print preview dan Word export tidak menampilkan teks placeholder tanda tangan yang belum signed.
* Workflow dan status pengesahan tidak berubah.
* Tidak ada perubahan database.

### Kebutuhan Database

Tidak ada perubahan database

### Checklist Pengujian

* [x] php -l chr_sop_export_render.php sukses
* [x] Preview HTML rid 33 tidak memuat teks Menunggu tanda tangan
* [x] Word preview rid 33 tidak memuat teks Menunggu tanda tangan
* [x] Area tanda tangan unsigned tetap menyisakan ruang kosong
* [ ] Cek manual preview di browser
* [ ] Cek manual Word export di aplikasi Word

## 2026-07-12 16:41:05 - Pratinjau dokumen CHR dalam modal

### File Diubah

* review.php
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Mengubah tombol Pratinjau Dokumen agar membuka popup/modal melayang di halaman review.
* Menambahkan modal Bootstrap dengan iframe untuk menampilkan preview dokumen CHR tanpa tab baru.
* Membersihkan iframe saat modal ditutup.
* Mempertahankan tombol unduh Word/PDF tetap membuka/unduh seperti sebelumnya.

### Dampak

* Pratinjau CHR lebih cepat dilihat tanpa meninggalkan halaman review.
* Tidak mengubah workflow, data_json, pengesahan, atau endpoint export.
* Tidak ada perubahan database.

### Kebutuhan Database

Tidak ada perubahan database

### Checklist Pengujian

* [x] php -l review.php sukses
* [x] Tombol Pratinjau Dokumen tidak lagi memakai target tab baru
* [x] Modal dan iframe pratinjau tersedia di markup halaman
* [ ] Klik Pratinjau Dokumen di browser membuka modal
* [ ] Tombol close menutup modal dan membersihkan iframe
* [ ] Unduh Word/PDF tetap normal

## 2026-07-12 18:03:22 - Penutupan uji CHR Manajemen Risiko

### File Diubah

* chr_sop_export_common.php
* chr_sop_export_render.php
* chr_sop_export.php
* chr_sop_export_pdf.php
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Menambahkan wrapper ekspor netral `chr_approval_export_*` untuk helper yang dipakai bersama CHR SOP, RKAKL, dan Manajemen Risiko.
* Mempertahankan fungsi lama `chr_sop_export_*` sebagai kompatibilitas endpoint lama.
* Menghapus baris status pengesahan dari HTML/DOCX ekspor agar dokumen tidak memuat teks workflow seperti menunggu tanda tangan.
* Menguji save/reload repeater Komponen Reviu dan Hasil Temuan pada rid=32, lalu mengembalikan data asli setelah uji.

### Dampak

* Ekspor CHR Manajemen Risiko memakai API helper yang lebih netral tanpa mengubah perilaku SOP/RKAKL.
* Preview dan ekspor dokumen tetap mendukung tanda tangan offline karena area tanda tangan kosong tidak diberi teks menunggu tanda tangan.
* Tidak ada perubahan database.

### Kebutuhan Database

Tidak ada perubahan database

### Checklist Pengujian

* [x] Repeater Komponen Reviu bisa ditambah dan disimpan
* [x] Repeater Komponen Reviu bisa dikurangi dan disimpan
* [x] Repeater Hasil Temuan bisa ditambah dan disimpan
* [x] Repeater Hasil Temuan bisa dikurangi dan disimpan
* [x] Save/reload rid=32 tidak kehilangan data uji
* [x] Workflow fungsi pengesahan berjalan sampai waiting_signatures, partially_signed, approved, returned, reopen, dan ajukan ulang
* [x] Preview HTML draft memuat watermark draft
* [x] Preview HTML/DOCX tidak memuat teks menunggu tanda tangan
* [x] Final export tetap ditolak jika dokumen belum approved
* [ ] Uji klik browser desktop/mobile secara manual
* [ ] Uji login sebagai masing-masing signer secara manual

## 2026-07-12 18:28:10 - Tambahkan enam template CHR tersisa

### File Diubah

* chr_template_pengembangan_pegawai.php
* chr_template_lhkpn_lhkasn.php
* chr_template_iku_ikt.php
* chr_template_lkj.php
* chr_template_pipk.php
* chr_template_rkbmn.php
* chr_helpers.php
* chr_templates.php
* chr_form_renderer.php
* chr_sop_export_common.php
* review.php
* db/schema_bootstrap.php
* deploy/migrations/20260712_181500_ensure_jenis_reviu_remaining_chr.sql
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Menambahkan template dinamis untuk CHR Pengembangan Pegawai, LHKPN/LHKASN, IKU-IKT, Laporan Kinerja, PIPK Tingkat Satker, dan RKBMN.
* Mengaktifkan seluruh template baru pada registry dan mapping jenis reviu.
* Memakai renderer dinamis, data_json dynamic, pengesahan standar, workflow bersama, dan export approval bersama.
* Menambahkan dukungan input/validasi angka untuk field numerik pada repeater.
* Menambahkan migration jenis reviu untuk enam template baru dan menjalankannya lokal.
* Membuat data uji lokal untuk masing-masing template baru dan menguji save/reload serta export preview.

### Dampak

* Enam jenis CHR tersisa dapat memakai template dinamis, picker pegawai, workflow pengesahan, dan export preview/final standar.
* CHR SOP, RKAKL, Manajemen Risiko, dan legacy Laporan Keuangan tetap diregresi tanpa perubahan struktur template.
* Tidak mengubah tabel reviu_chr.

### Kebutuhan Database

Migration baru: deploy/migrations/20260712_181500_ensure_jenis_reviu_remaining_chr.sql
Migration sudah dijalankan lokal.

### Checklist Pengujian

* [x] Template Pengembangan Pegawai resolve ke chr_pengembangan_pegawai
* [x] Template LHKPN/LHKASN resolve ke chr_lhkpn_lhkasn
* [x] Template IKU-IKT resolve ke chr_iku_ikt
* [x] Template Laporan Kinerja resolve ke chr_lkj
* [x] Template PIPK resolve ke chr_pipk
* [x] Template RKBMN resolve ke chr_rkbmn
* [x] Save/reload data uji lokal seluruh template berhasil
* [x] Repeater utama seluruh template tersimpan dan terbaca ulang
* [x] Workflow pengesahan in-memory seluruh template berjalan sampai waiting_signatures, partially_signed, approved, returned, dan reopen draft
* [x] Preview HTML seluruh template memuat watermark draft
* [x] Word preview seluruh template berhasil dibuat
* [x] Preview/export tidak memuat teks Menunggu tanda tangan
* [x] Regresi SOP/RKAKL/Manajemen Risiko berhasil
* [x] Regresi legacy Laporan Keuangan tetap resolve ke chr_legacy_laporan_keuangan
* [ ] Uji klik browser desktop/mobile seluruh template
* [ ] Uji tanda tangan dari akun masing-masing signer di browser
* [ ] Uji PDF print preview manual
* [ ] Uji Word final/PDF final setelah approved secara manual

## 2026-07-13 08:35:45 - Persiapan Git final CHR

### File Diubah

* .gitignore
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Menambahkan pengecualian backup Codex lokal agar tidak masuk daftar file siap commit.
* Menambahkan pola ignore artefak ekspor/uji dokumen CHR seperti DOCX/PDF preview, draft, final, dan screenshot lokal.
* Mempertahankan migration SQL di deploy/migrations agar tetap bisa masuk Git.

### Dampak

* Persiapan commit lebih bersih dan file uji lokal tidak ikut tersiapkan.
* Tidak mengubah fungsi template CHR, workflow, export, login, atau database.

### Kebutuhan Database

Tidak ada perubahan database

### Checklist Pengujian

* [ ] git status tidak menampilkan .codex/backups sebagai untracked
* [ ] file source CHR tetap terlihat sebagai kandidat commit
* [ ] migration SQL deploy/migrations tetap tidak ter-ignore
* [ ] artefak ekspor/uji lokal tidak ikut masuk Git
* [ ] git diff --check lulus

## 2026-07-13 09:21:29 - Clean URL pra-Git SIKAT

### File Diubah

* .htaccess
* public/.htaccess
* includes/url_helpers.php
* includes/auth.php
* includes/session_hardening.php
* includes/topbar.php
* bootstrap.php
* index.php
* login.php
* dashboard.php
* navbar.php
* review.php
* pengguna.php
* kebijakan.php
* risiko.php
* self_assessment.php
* settings.php
* pelaporan.php
* pelaporan_detail.php
* public_media.php
* public_contacts.php
* public/public_media.php
* public/public_contacts.php
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Menambahkan helper URL terpusat untuk base path, route, review tab, dan asset.
* Menambahkan rewrite clean URL di root dan public htaccess dengan kompatibilitas URL lama.
* Memperbarui link internal utama dan redirect login/logout/dashboard/review agar memakai URL bersih.
* Menambahkan wrapper public untuk kelola media dan kontak publik.
* Mempertahankan endpoint teknis export/download dengan URL lama agar kompatibel.

### Dampak

* Halaman utama dapat memakai URL bersih seperti /login, /dashboard, /review/jadwal, /review/chr/{rid}, /pelaporan, /pengguna, dan /settings.
* URL lama .php tetap tersedia selama transisi.
* Tidak ada perubahan database, template CHR, workflow, login/session, atau struktur tabel.

### Kebutuhan Database

Tidak ada perubahan database

### Checklist Pengujian

* [ ] /ski_new/login terbuka
* [ ] /ski_new/dashboard terbuka
* [ ] /ski_new/review/jadwal terbuka
* [ ] /ski_new/review/chr/{rid} terbuka untuk rid numerik
* [ ] URL lama review.php?tab=jadwal tetap terbuka
* [ ] Login dan logout tetap berfungsi
* [ ] Link topbar/navbar tidak menampilkan .php untuk halaman utama
* [ ] Asset logo/CSS tetap termuat
* [ ] Export/download teknis tetap berfungsi
* [ ] Tidak ada redirect loop

## 2026-07-13 09:57:02 - Perbaiki asset clean URL nested

### File Diubah

* includes/head_favicon.php
* dashboard.php
* review.php
* login.php
* kebijakan.php
* pengguna.php
* risiko.php
* self_assessment.php
* pelaporan.php
* pelaporan_detail.php
* public_media.php
* public_contacts.php
* settings.php
* mail_recipients.php
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Mengubah pemanggilan CSS, JS, favicon, dan logo lokal yang masih relatif agar memakai helper asset_url.
* Memastikan halaman clean URL nested seperti /review/chr/{rid} tidak mencari asset di bawah path nested.
* Mengganti favicon hardcoded /ski_new menjadi base path dinamis.
* Mempertahankan clean URL dan URL lama tanpa perubahan database atau struktur CHR.

### Dampak

* Header/topbar dan styling halaman nested clean URL kembali termuat normal.
* URL asset lokal menjadi /ski_new/assets/... atau /ski_new/asset/... pada XAMPP.
* Pada DocumentRoot public, helper menghasilkan /assets/... atau /asset/... sesuai base path kosong.

### Kebutuhan Database

Tidak ada perubahan database

### Checklist Pengujian

* [ ] /ski_new/review/chr/{rid} menampilkan CSS/topbar normal setelah login
* [ ] /ski_new/review/jadwal menampilkan CSS/topbar normal
* [ ] /ski_new/review/master menampilkan CSS/topbar normal
* [ ] /ski_new/dashboard menampilkan CSS/topbar normal
* [ ] /ski_new/review.php?tab=chr&rid={rid} tetap normal
* [ ] CSS lokal menghasilkan HTTP 200
* [ ] JS lokal menghasilkan HTTP 200
* [ ] Logo dan favicon menghasilkan HTTP 200
* [ ] Tidak ada CSS/JS 404 di Network

## 2026-07-13 10:05:44 - Perbaiki endpoint export clean URL nested

### File Diubah

* includes/url_helpers.php
* review.php
* login.php
* pelaporan_detail.php
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Menambahkan helper endpoint_url untuk membangun URL absolut endpoint teknis dari base aplikasi.
* Memperbaiki tombol Pratinjau Dokumen, Word/PDF pratinjau, dan Word/PDF final agar tidak relatif terhadap /review/chr/{rid}.
* Memperbaiki endpoint export/download dokumen, CHR legacy, laporan, verifikasi, dan lampiran agar memakai base URL aplikasi.
* Menambahkan reset iframe dan pesan error ramah pada modal pratinjau CHR.

### Dampak

* Tombol preview/export pada clean URL nested tidak lagi mengarah ke /review/chr/endpoint.php.
* URL lama tetap kompatibel.
* Tidak ada perubahan database, struktur CHR, workflow, atau validasi akses endpoint.

### Kebutuhan Database

Tidak ada perubahan database

### Checklist Pengujian

* [ ] Pratinjau Dokumen dari /review/chr/{rid} tidak 404
* [ ] Word Pratinjau dari /review/chr/{rid} tidak 404
* [ ] PDF Pratinjau dari /review/chr/{rid} tidak 404
* [ ] Word/PDF Final tetap hanya tersedia saat approved
* [ ] URL lama review.php?tab=chr&rid={rid} tetap berfungsi
* [ ] Download dokumen/lampiran tidak mencari endpoint di path nested
* [ ] Export laporan dan verifikasi tidak mencari endpoint di path nested
* [ ] Modal preview reset iframe saat ditutup

## 2026-07-13 10:09:33 - Arahkan tanda tangan dari daftar dokumen menunggu

### File Diubah

* chr_helpers.php
* chr_form_renderer.php
* review.php
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Menambahkan anchor stabil untuk blok pengesahan, pejabat menyetujui, ketua tim, dan anggota tim.
* Mengubah tombol Lihat Dokumen pada daftar Dokumen Menunggu Tanda Tangan agar membawa fragment sesuai posisi signer.
* Menambahkan smooth scroll, highlight sementara, dan fokus ke area aksi tanda tangan saat halaman CHR dibuka dengan anchor pengesahan.
* Menambahkan fallback ke section pengesahan umum jika anchor spesifik tidak ditemukan.

### Dampak

* Pengguna langsung diarahkan ke area tanda tangan yang perlu dikerjakan.
* Tidak mengubah workflow, validasi signer, struktur JSON, database, atau keamanan server.
* Clean URL dan URL lama tetap kompatibel.

### Kebutuhan Database

Tidak ada perubahan database

### Checklist Pengujian

* [ ] Pejabat Menyetujui diarahkan ke #approval-approving-official
* [ ] Ketua Tim diarahkan ke #approval-team-leader
* [ ] Anggota Tim pertama diarahkan ke #approval-team-member-0
* [ ] Anggota Tim tambahan diarahkan ke #approval-team-member-{index}
* [ ] Anchor yang tidak ditemukan fallback ke #approval-section
* [ ] Highlight sementara tampil 2-3 detik
* [ ] Area aksi tanda tangan terfokus jika tersedia
* [ ] Clean URL tetap berfungsi
* [ ] URL lama dengan fragment tetap berfungsi
## 2026-07-13 10:18:09 - Ubah menu utama menjadi sidebar profesional

### File Diubah

* includes/sidebar.php
* includes/topbar.php
* navbar.php
* assets/css/ui_base.css
* public/assets/css/ui_base.css
* dashboard.php
* pelaporan.php
* public_media.php
* public_contacts.php
* settings.php
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Menambahkan komponen sidebar global untuk halaman setelah login.
* Menyederhanakan topbar menjadi tombol sidebar, judul halaman, dan menu profil.
* Mengubah navbar lama menjadi wrapper kompatibilitas agar tidak merender menu horizontal duplikat.
* Menambahkan collapse sidebar desktop, drawer mobile, backdrop, Escape close, dan preferensi localStorage.
* Menghapus quick menu dashboard yang menduplikasi sidebar dan memperbaiki beberapa link Dashboard agar memakai route_url.

### Dampak

* Menu utama aplikasi berpindah dari horizontal atas menjadi sidebar kiri yang konsisten.
* Clean URL tetap digunakan pada link menu dan submenu Review.
* Tidak ada perubahan database, role, workflow CHR, export, tanda tangan, session, atau validasi server.

### Kebutuhan Database

Tidak ada perubahan database

### Checklist Pengujian

* [ ] Dashboard tampil dengan sidebar
* [ ] Review Jadwal tampil dengan sidebar dan submenu aktif
* [ ] Review CHR clean URL tampil tanpa asset rusak
* [ ] Pelaporan tampil dengan sidebar
* [ ] Pengguna/Kebijakan/Risiko/Self-Assessment tampil dengan sidebar
* [ ] Kelola Media Publik dan Kontak Publik tampil untuk admin
* [ ] Sidebar collapse desktop bekerja
* [ ] Drawer mobile bisa dibuka/tutup
* [ ] Logout dan Ubah Password tetap bisa diakses
* [ ] Tidak ada menu horizontal utama yang dobel
* [ ] Tidak ada perubahan role atau akses server
## 2026-07-13 10:51:25 - Perbaiki regresi render sidebar global

### File Diubah

* includes/topbar.php
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Menambahkan pemuatan CSS layout sidebar dengan cache-busting tepat sebelum sidebar dirender.
* Menambahkan critical CSS fallback agar sidebar tetap fixed dan tidak tampil sebagai daftar link mentah ketika cache CSS lama atau JavaScript belum siap.
* Menandai topbar sebagai app-header tanpa mengubah fungsi profil atau session.
* Mempertahankan satu komponen sidebar global dan wrapper kompatibilitas navbar lama.

### Dampak

* Sidebar tidak lagi bergantung penuh pada cache stylesheet lama atau JavaScript untuk layout dasar.
* Dashboard dan halaman nested clean URL lebih tahan terhadap CSS stale/cache.
* Tidak ada perubahan database, role, clean URL, CHR, export, tanda tangan, atau workflow.

### Kebutuhan Database

Tidak ada perubahan database

### Checklist Pengujian

* [ ] Dashboard tampil dengan sidebar fixed kiri
* [ ] Sidebar tidak tampil sebagai teks/link mentah
* [ ] Topbar hanya tampil satu kali sebagai header sederhana
* [ ] Konten dashboard tidak tertutup sidebar
* [ ] /review/chr/36 tetap memuat layout sidebar
* [ ] ui_base.css menghasilkan HTTP 200
* [ ] Logo sidebar menghasilkan HTTP 200
* [ ] Collapse desktop tetap bekerja
* [ ] Drawer mobile tetap bekerja
* [ ] Tidak ada perubahan hak akses menu
## 2026-07-13 11:55:49 - Penyempurnaan final sidebar SIKAT

### File Diubah

* includes/sidebar.php
* includes/topbar.php
* assets/css/ui_base.css
* public/assets/css/ui_base.css
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Memperbesar dan merapikan logo, judul SIKAT, subtitle, serta badge versi pada header sidebar.
* Mengganti ikon huruf placeholder dengan ikon SVG inline profesional untuk setiap menu.
* Membuat submenu Review Internal sebagai accordion dengan status terbuka otomatis saat route Review aktif.
* Menambahkan tombol close mobile, tooltip title pada menu/collapse, dan resize event setelah collapse/drawer berubah.
* Merapikan spacing, alignment, mode collapsed, dan drawer mobile tanpa mengubah warna utama.

### Dampak

* Sidebar tampil lebih rapi, profesional, dan konsisten di desktop maupun mobile.
* Menu tetap mengikuti role dan akses yang sudah ada.
* Tidak ada perubahan database, clean URL, CHR, workflow, export, session, atau validasi server.

### Kebutuhan Database

Tidak ada perubahan database

### Checklist Pengujian

* [ ] Logo sidebar lebih besar dan proporsional
* [ ] Ikon menu tampil sebagai SVG konsisten
* [ ] Review Internal menampilkan accordion submenu
* [ ] Submenu Review terbuka otomatis pada route Review
* [ ] Mode collapsed menyembunyikan label dan tetap menampilkan ikon
* [ ] Konten utama melebar saat sidebar collapsed
* [ ] Drawer mobile tertutup default dan bisa ditutup dengan tombol X
* [ ] Backdrop dan Escape menutup drawer mobile
* [ ] Body lock aktif saat drawer mobile terbuka
* [ ] Route /dashboard, /review/jadwal, /review/chr/36, /pelaporan, /pengguna, /settings tetap normal
## 2026-07-13 12:13:51 - Ubah urutan menu Kebijakan sidebar

### File Diubah

* includes/sidebar.php
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Memindahkan menu Kebijakan agar tampil di bawah Pelaporan pada sidebar.
* Mempertahankan URL, ikon, active state, dan hak akses menu yang sudah ada.

### Dampak

* Urutan menu Kepatuhan Internal menjadi Review Internal, Pelaporan, Kebijakan, Risiko, Self-Assessment.
* Tidak ada perubahan fungsi, role, database, clean URL, CHR, atau workflow.

### Kebutuhan Database

Tidak ada perubahan database

### Checklist Pengujian

* [ ] Menu Kebijakan tampil di bawah Pelaporan
* [ ] Link Kebijakan tetap membuka halaman kebijakan
* [ ] Link Pelaporan tetap membuka halaman pelaporan
* [ ] Active state Kebijakan dan Pelaporan tetap normal
## 2026-07-13 12:19:45 - Rapikan logo dan perilaku sidebar final

### File Diubah

* includes/sidebar.php
* includes/topbar.php
* assets/css/ui_base.css
* public/assets/css/ui_base.css
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Memperbesar sedikit logo sidebar dan merapikan jarak logo, judul SIKAT, subtitle, serta badge V3.0.
* Merapikan profil pengguna bawah sidebar agar tidak terpotong pada mode normal maupun collapsed.
* Menambahkan tooltip profil dan memperjelas tooltip tombol collapse.
* Menutup drawer mobile otomatis saat menu sidebar diklik.
* Memicu resize event setelah collapse/drawer berubah agar chart dapat menyesuaikan lebar konten.

### Dampak

* Sidebar lebih rapi pada desktop normal, collapsed, dan mobile drawer.
* Urutan menu tidak berubah.
* Tidak ada perubahan database, hak akses, clean URL, CHR, export, tanda tangan, workflow, atau route.

### Kebutuhan Database

Tidak ada perubahan database

### Checklist Pengujian

* [ ] Logo sidebar lebih besar dan tetap proporsional
* [ ] Badge V3.0 sejajar rapi
* [ ] Tooltip tombol collapse tampil jelas
* [ ] Tooltip profil tersedia
* [ ] Mode collapsed hanya menampilkan ikon
* [ ] Profil bawah tetap rapi saat collapsed
* [ ] Klik menu pada mobile menutup drawer
* [ ] Chart dashboard resize setelah collapse/drawer
* [ ] Submenu Review tetap terbuka pada route Review
## 2026-07-13 12:23:46 - Perbesar logo sidebar

### File Diubah

* includes/topbar.php
* assets/css/ui_base.css
* public/assets/css/ui_base.css
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Memperbesar logo Kemenkes/Poltekkes pada header sidebar agar lebih terlihat.
* Menyesuaikan ukuran logo mode collapsed agar tetap proporsional.
* Menjaga tinggi header sidebar tetap compact.

### Dampak

* Identitas logo pada sidebar lebih jelas.
* Tidak ada perubahan menu, database, hak akses, clean URL, CHR, workflow, atau fungsi aplikasi.

### Kebutuhan Database

Tidak ada perubahan database

### Checklist Pengujian

* [ ] Logo sidebar lebih besar dan tetap proporsional
* [ ] Header sidebar tidak terlalu tinggi
* [ ] Mode collapsed tetap rapi
## 2026-07-13 13:35:22 - Samakan warna header dan sidebar

### File Diubah

* assets/css/ui_base.css
* public/assets/css/ui_base.css
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Menyamakan warna header atas dengan palet hijau tua sidebar.
* Menambahkan gradasi hijau halus dan shadow ringan pada header.
* Menyesuaikan hover tombol sidebar agar konsisten dengan header gelap.

### Dampak

* Header dan sidebar terlihat lebih menyatu secara visual.
* Tidak ada perubahan menu, fungsi, database, hak akses, clean URL, CHR, export, atau workflow.

### Kebutuhan Database

Tidak ada perubahan database

### Checklist Pengujian

* [ ] Warna header atas konsisten dengan sidebar
* [ ] Tombol sidebar tetap terlihat jelas
* [ ] Avatar tetap kontras
* [ ] Tidak ada perubahan layout menu

## 2026-08-03 10:29:41 - Perapian tabel pelaporan

### File Diubah

* pelaporan.php
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Menggabungkan kolom kategori, isi ringkas, dan lampiran menjadi tampilan ringkas dalam satu kolom.
* Merapikan kolom aksi dengan tombol Detail, aksi utama, dropdown Riwayat/aksi tambahan, dan modal konfirmasi hapus.
* Memindahkan input catatan aksi status ke modal bersama agar tabel tidak terlalu padat.
* Mempertahankan tombol Lihat Isi Lengkap agar tetap membuka modal detail pada bagian isi laporan.

### Dampak

* Tabel pelaporan lebih ringkas dan mudah dipindai.
* Fungsi status, catatan Kepala SKI/Direktur, tindak lanjut, riwayat, detail, dan hapus tetap memakai handler lama.
* Tidak mengubah workflow, database, hak akses, dashboard, Review, sidebar, ekspor, atau fungsi form publik.

### Kebutuhan Database

Tidak ada perubahan database

### Checklist Pengujian

* [ ] Halaman Pelaporan terbuka normal
* [ ] Tabel tampil dengan kolom Kode, Kategori & Isi, Dibuat, Status, Early Warning, Status TL, dan Aksi
* [ ] Lihat Detail membuka popup detail laporan
* [ ] Lihat Isi Lengkap membuka popup detail dan fokus ke isi laporan
* [ ] Riwayat membuka popup detail dan fokus ke riwayat
* [ ] Teruskan ke Kepala SKI membuka modal catatan dan submit tetap memproses status lama
* [ ] Aksi lain di dropdown tetap memproses status lama
* [ ] Hapus Laporan membuka modal konfirmasi sebelum menghapus
* [ ] Status TL tetap bisa diperbarui oleh Direktur
* [ ] Tampilan desktop rapi
* [ ] Tampilan mobile tetap bisa discroll tanpa layout pecah
* [ ] Tidak ada fatal error
* [ ] Tidak ada perubahan database
## 2026-08-03 10:32:39 - Singkatkan label Kepala SKI

### File Diubah

* pelaporan.php
* pelaporan_helpers.php
* login.php
* pengguna.php
* review.php
* laporan_export.php
* verifikasi_export.php
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Mengubah label tampilan “Kepala SKI” menjadi “Ka SKI” agar lebih singkat.
* Mempertahankan key role/backend seperti kepala_ski tanpa perubahan.

### Dampak

* Label role, status, form, dan export terkait SKI tampil lebih ringkas.
* Tidak mengubah fungsi login, role, workflow, database, atau validasi.

### Kebutuhan Database

Tidak ada perubahan database

### Checklist Pengujian

* [ ] Label Ka SKI tampil pada halaman terkait
* [ ] Role kepala_ski tetap berfungsi
* [ ] Pelaporan tetap normal
* [ ] Review tetap normal
* [ ] Export laporan/verifikasi tetap normal
* [ ] Tidak ada fatal error
* [ ] Tidak ada perubahan database
## 2026-08-03 10:46:09 - Ringkas baris pelaporan dan preview lampiran

### File Diubah

* pelaporan.php
* includes/report_detail_modal.php
* assets/js/report_detail_modal.js
* public/assets/js/report_detail_modal.js
* assets/css/ui_base.css
* public/assets/css/ui_base.css
* CHANGELOG_PATCH.md

### Ringkasan Perubahan

* Membatasi ringkasan isi laporan pada tabel menjadi 2 baris dengan line-clamp dan ellipsis.
* Membatasi catatan tindak lanjut pada tabel menjadi 2 baris dan menambahkan modal baca-saja untuk catatan lengkap.
* Mengubah tombol lampiran pada tabel agar membuka modal detail pada bagian Lampiran.
* Menambahkan preview lampiran inline di modal detail untuk gambar dan PDF, dengan fallback unduh untuk tipe lain.

### Dampak

* Tinggi baris tabel pelaporan lebih ringkas dan mudah dipindai.
* Isi laporan, catatan TL, riwayat, dan lampiran lengkap tetap dapat dibuka dari modal.
* Tidak mengubah data database, workflow, status, early warning, CSRF, export, hak akses, atau endpoint lampiran.

### Kebutuhan Database

Tidak ada perubahan database

### Checklist Pengujian

* [ ] Isi laporan pendek tampil normal
* [ ] Isi laporan panjang terpotong 2 baris di tabel
* [ ] Lihat Isi Lengkap membuka modal detail isi laporan
* [ ] Catatan TL pendek tampil normal
* [ ] Catatan TL panjang terpotong 2 baris di tabel
* [ ] Lihat Catatan Lengkap membuka modal catatan TL
* [ ] Laporan tanpa lampiran tidak menampilkan tombol lampiran
* [ ] Laporan dengan lampiran membuka modal pada bagian Lampiran
* [ ] Preview JPG/PNG/WEBP/GIF tampil inline jika file tersedia
* [ ] Preview PDF tampil dalam iframe jika browser mendukung
* [ ] DOC/DOCX/XLS/XLSX/ZIP menampilkan fallback pratinjau tidak tersedia
* [ ] Tombol Unduh lampiran tetap berfungsi
* [ ] Filter, Reset, Export CSV, dan Export Excel tetap normal
* [ ] Riwayat, aksi status, status TL, dan hapus laporan tetap normal
* [ ] Tampilan desktop rapi
* [ ] Tampilan mobile rapi
* [ ] Tidak ada fatal error
* [ ] Tidak ada perubahan database