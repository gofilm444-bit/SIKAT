# QUICK FIX PATCH PLAN (1–2 Hari)

## Target
- Mengurangi risiko tertinggi tanpa perubahan arsitektur besar.

## Scope
1) Tambah CSRF token di semua modul admin CRUD.
2) Ubah delete action dari GET ? POST.
3) Harden output export (escape HTML) untuk cegah XSS ringan.
4) Matikan `display_errors` saat `APP_ENV=production`.
5) Blok akses langsung helper PHP via `.htaccess`/nginx (atau pindahkan ke `/includes`).

## Langkah Teknis
1) **CSRF Helper Terpusat**
   - Buat helper CSRF (jika belum) di `includes/auth.php` atau `includes/csrf.php`.
   - Inject hidden token ke semua form POST.

2) **Refactor Delete**
   - `kebijakan.php`, `pengguna.php`, `risiko.php`, `self_assessment.php`
   - Ganti link delete menjadi `<form method="post">` + token.
   - Handle POST delete di server.

3) **Escape Export Output**
   - Escape semua output HTML/Word/Excel di `kebijakan.php` (dan modul lain jika ada export serupa).

4) **Production Error Handling**
   - Bungkus `ini_set('display_errors',1)` hanya saat `APP_ENV=local/dev`.

5) **Block Helper Access**
   - Update `.htaccess` dan `deploy/nginx_hardening_snippet.conf` untuk deny `*_helpers.php`.

## Test Plan
- Login sebagai admin, coba create/edit/delete pada kebijakan/risiko/self-assessment/pengguna.
- Pastikan delete via GET tidak lagi bekerja.
- Uji export HTML/Word/Excel tampil normal dan tidak menjalankan script.
- Pastikan tidak ada error di login/halaman utama.

## File yang Akan Diubah
- `kebijakan.php`
- `pengguna.php`
- `risiko.php`
- `self_assessment.php`
- `includes/*` (CSRF helper jika dibuat)
- `.htaccess`
- `deploy/nginx_hardening_snippet.conf`
- `dashboard.php`

