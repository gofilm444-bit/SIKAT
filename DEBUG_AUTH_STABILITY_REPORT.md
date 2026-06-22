# DEBUG_AUTH_STABILITY_REPORT

Tanggal: 2026-01-27

## Ringkasan Temuan
- Logout tidak konsisten karena state auth tersimpan di beberapa key session (auth/user/admin/pegawai) dan tidak selalu diset ulang secara konsisten.
- Setelah ganti password, session biasanya ikut di-refresh sehingga logout terlihat ?membaik?.
- Perbedaan cookie path/domain juga bisa membuat cookie lama tetap terbaca walau logout dilakukan dari halaman berbeda.

## Bukti & Observasi
- Guard utama sekarang memakai `$_SESSION['auth']`, tetapi beberapa flow lama masih bergantung `$_SESSION['user']`.
- Logout sebelumnya mengandalkan handler di file berbeda dan kadang gagal ketika session sudah kadaluarsa.
- Password change di `pengguna.php` hanya mengubah `$_SESSION['user']` tanpa menyelaraskan `$_SESSION['auth']`.

## Perbaikan yang Diterapkan
1) **Single source of truth session**
   - Tambah `establish_login_session()` untuk menyusun `$_SESSION['auth']` dan menyelaraskan legacy `$_SESSION['user']`.
   - Dipakai pada login sukses dan saat ganti password.

2) **Logout deterministik**
   - Semua jalur logout memakai `force_logout_and_redirect()` sehingga selalu menghapus session + cookie dengan path fallback.

3) **Debug logger (local only)**
   - Menambahkan `auth_debug_log()` yang aktif hanya jika `APP_ENV=local` dan `APP_DEBUG_AUTH=1`.
   - Log disimpan di `storage/logs/auth_debug.log`.

## File yang Terpengaruh
- `includes/session_hardening.php`
- `login.php`
- `logout.php`
- `bootstrap.php`
- `pengguna.php`

## Cara Mengaktifkan Debug (Local)
Tambahkan di `.env`:
```
APP_ENV=local
APP_DEBUG_AUTH=1
```
Lalu cek log:
`storage/logs/auth_debug.log`

## Catatan Stabilitas
- Rate limit disederhanakan (per IP+username saja) untuk menghindari lock palsu pada jaringan bersama.
- Timeout idle/absolute tetap aktif dan tidak mengganggu logout.
