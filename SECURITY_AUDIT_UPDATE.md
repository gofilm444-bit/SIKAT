# Security Audit Update (SIKAT)

## Ringkasan Posture Saat Ini
Secara umum, aplikasi sudah memakai session hardening, rate‑limit login, CSRF token di beberapa modul, prepared statements untuk sebagian besar query, serta header keamanan dasar. Namun masih ada risiko tinggi yang berkaitan dengan file sensitif di webroot dan endpoint tooling lokal yang seharusnya tidak tersedia di produksi. Ada juga risiko XSS akibat output yang tidak konsisten di‑escape, serta kemungkinan bypass akses jika file unggahan bisa diakses langsung tanpa kontrol.

## Temuan (P0 / P1 / P2)

### P0 — Kredensial Superadmin Tersedia di Webroot
- **Lokasi/Bukti:** `LOCAL_ADMIN_CREDENTIALS.md` (berisi username/password superadmin).
- **Dampak:** Jika file dapat diakses publik, attacker bisa mengambil kredensial admin dan menguasai sistem.
- **Status:** **Mitigated (2026-01-27)** — dipindah ke `storage/private/` dan file di webroot diganti placeholder; instruksi rotasi disediakan tanpa rahasia.
- **Rekomendasi:**
  - Hapus file ini dari webroot / pindahkan ke lokasi non‑public.
  - Tambahkan deny rule pada web server untuk `*.md` atau file kredensial.
  - Segera rotasi password superadmin.

### P1 — Endpoint Tooling Lokal di Folder Publik
- **Lokasi:** `tools/create_superadmin.php`, `tools/reset_lockout.php`, `tools/security_selfcheck.php`, `tools/health.php`.
- **Dampak:** Jika `APP_ENV` salah konfigurasi atau token bocor, endpoint ini dapat dipakai untuk eskalasi akses atau reset keamanan.
- **Status:** **Mitigated (2026-01-27)** — guard `APP_ENV=local` + token di semua tools, serta aturan blokir server (Apache/Nginx) ditambahkan.
- **Rekomendasi:**
  - Blokir akses `/tools` lewat web server di production.
  - Jadikan tooling hanya CLI (tidak bisa diakses via HTTP).
  - Pastikan `APP_RESET_TOKEN` tidak sama dengan contoh dan tidak disimpan di webroot.

### P1 — Potensi Bypass Akses via Direct File Access
- **Lokasi:** direktori `uploads/`, `upload/` (tidak diblokir di `.htaccess`).
- **Dampak:** Dokumen sensitif (reviu/pelaporan) dapat diunduh langsung jika URL diketahui, tanpa otorisasi.
- **Status:** **Mitigated (2026-01-27)** — akses langsung diblokir via `.htaccess` dan nginx hardening snippet.
- **Rekomendasi:**
  - Simpan file di luar webroot, atau
  - Tambah rule deny akses langsung ke `/uploads` dan `/upload`, dan
  - Hanya izinkan download melalui endpoint terotentikasi (mis. `download.php`, `attachment_download.php`).

### P2 — Output Tidak Konsisten Di‑Escape (XSS Risk)
- **Lokasi indikatif:** `pelaporan.php`, `review.php`, `kebijakan.php`, `pengguna.php`, `risiko.php`, `self_assessment.php` (beberapa `<?= ... ?>` tanpa `e()/htmlspecialchars`).
- **Dampak:** Jika data dari DB atau input user tidak disanitasi, bisa terjadi stored XSS.
- **Rekomendasi:**
  - Terapkan escaping output standar (helper `e()`), terutama untuk field yang berasal dari DB/user input.
  - Audit seluruh template agar konsisten.

### P2 — Kebijakan Header Keamanan Masih Longgar
- **Lokasi:** `includes/security_headers.php`.
- **Catatan:** CSP masih memperbolehkan `unsafe-inline` dan `http:` serta `https:` umum.
- **Rekomendasi:**
  - Perketat CSP secara bertahap (nonce/hash untuk inline, batasi domain).
  - Tambahkan HSTS di production.

### P2 — Inkonistensi Guard Akses & Role Handling
- **Lokasi:** beberapa halaman memakai `require_login()` dari `bootstrap.php`, namun ada juga guard manual (`if(!isset($_SESSION['user'])) ...`).
- **Dampak:** Risiko bug akses/role mismatch saat fitur bertambah.
- **Rekomendasi:**
  - Standarkan guard akses melalui satu helper.
  - Pastikan role normalization konsisten (mis. `auth_normalize_role`).

## Regression Watchlist
- **Session timeout & logout:** perubahan di `includes/session_hardening.php` / `bootstrap.php` dapat memicu lockout tidak terduga.
- **Download/Export endpoints:** `download.php`, `attachment_download.php`, `laporan_export.php`, `dokumen_export.php`, `chr_export*.php` harus selalu menjaga auth + path validation.
- **File upload paths:** `uploads/rekap`, `uploads/reviu`, `uploads/pelaporan` perlu kontrol akses konsisten.
- **Role mapping:** `pelaporan_actor_group()` dan `role_slug()` dipakai lintas modul; perubahan berisiko memecah akses.

## Rekomendasi Hardening Server (tanpa implementasi)
- Deny akses langsung ke `/tools`, `/uploads`, `/upload`, `/storage` via `.htaccess` atau konfigurasi virtual host.
- Tambahkan rule untuk block file sensitif (`*.md`, `*.env`, `*.bak`) di webroot.
- Aktifkan HSTS, dan batasi CSP ke domain yang benar‑benar dipakai.
- Pastikan `display_errors` nonaktif di production pada semua entrypoint.
- Audit permissions `storage/` agar log tidak dapat diakses publik.
