# DEBUG_LOGIN_REPORT

## Konfigurasi DB yang dipakai aplikasi
- Loader: `db.php` (dipanggil oleh `login.php`)
- Env loader: `config/env.php`
- DB config: `config/database.php`
- .env: (tidak ditemukan)

### Nilai aktif (tanpa password)
- DB_HOST: localhost
- DB_NAME: ski_db
- DB_USER: root

## Audit logic login (login.php)
- Query login: `SELECT id,nama,username,password_hash,password,peran,status FROM pengguna WHERE username=? LIMIT 1`
- Verifikasi password: prioritas `password_hash` (bcrypt), fallback `password` (plaintext)
- Rate limit: file-based per IP+username di `storage/login_rate`
- Tidak ada syarat tambahan (status/aktif) yang memblokir login

## Akar masalah yang terdeteksi
- Jika kolom `password_hash` terisi namun tidak cocok (mis. password diubah manual di kolom `password` saja), login gagal karena sistem hanya memeriksa `password_hash`.
- Banyak akun di tabel `pengguna` sudah memiliki `password_hash` (bcrypt). Jika admin mengganti password lewat DB tetapi tidak memperbarui `password_hash`, maka login akan selalu gagal meski password benar.

## Perbaikan yang diterapkan
- Login sekarang melakukan fallback ke kolom `password` ketika `password_hash` ada tetapi tidak cocok.
- Mendukung legacy hash MD5/SHA1 (jika ditemukan di kolom `password`) dan otomatis di-upgrade ke bcrypt.
- Setelah fallback sukses, sistem meng-update `password_hash` dan mengosongkan `password` (migrasi aman).

## Catatan rate-limit
- Lockout disimpan di `storage/login_rate/*.json`.
- Jika lockout mengganggu testing lokal, gunakan tool reset yang disediakan (lihat `tools/reset_lockout.php`).
