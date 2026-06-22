<!--
Deteksi struktur (2026-01-25):
- Folder upload: /upload, /uploads
- File koneksi DB: /db/koneksi.php, /db.php, /index.php (inline, kini pakai db/koneksi.php)
- File .sql ditemukan: /db/ski_db.sql, /db/siski_schema.sql (dipindah ke /storage_sql_backup)
- Folder sensitif: /db, /vendor, /storage_sql_backup, /cron
- File sampah terdeteksi sebelumnya: .DS_Store, ._*
-->

# SECURITY HARDENING NOTES

## Ringkasan Risiko Utama
- Directory listing terbuka di webroot dan folder upload.
- Akses langsung ke folder sensitif (db/, vendor/, config/) dan file rahasia (.env, .sql, backup).
- Eksekusi file PHP di folder upload.
- Kredensial DB tersimpan hard-coded di source.
- Header keamanan belum diset secara konsisten.

## Cara Deploy (Nginx)
- Tempel isi `deploy/nginx_hardening_snippet.conf` ke dalam `server { ... }` vhost.
- Pastikan `autoindex off;` aktif dan reload Nginx.
- Jika folder upload lain ditambahkan, tambahkan blok `location ^~ /folder/ { ... }` serupa.
- Disarankan menaruh `storage_sql_backup/` di luar webroot saat production.

## Cara Deploy (Apache)
- Pastikan `AllowOverride All` aktif untuk webroot agar `.htaccess` terbaca.
- Gunakan `.htaccess` di root untuk blok folder sensitif + dotfiles + ekstensi rahasia.
- Gunakan `.htaccess` di `/upload/` dan `/uploads/` untuk mematikan listing dan blok eksekusi PHP.
- Disarankan menaruh `storage_sql_backup/` di luar webroot saat production.

## Checklist Setelah Deploy
- Akses `https://domain/db/` harus 403/404.
- Akses `https://domain/db/ski_db.sql` harus 403/404 (file sudah dipindah).
- Akses `https://domain/uploads/` dan `https://domain/upload/` tidak boleh tampil listing.
- Akses `https://domain/.env` harus 403/404.
- Upload file `.php` ke uploads harus tidak bisa dieksekusi.
- Cek response headers (X-Frame-Options, CSP, dll) sudah muncul.
- Opsional: akses /tools/security_selfcheck.php (admin) lalu hapus file setelah selesai.
- Pastikan `.env` dibuat di server dan kredensial tidak lagi hard-coded.

## Catatan Konfigurasi DB
- File `.env` opsional, gunakan `.env.example` sebagai acuan.
- Kredensial fallback tetap kompatibel dengan nilai lama untuk menjaga aplikasi tetap berjalan.
- Jangan gunakan user `root` untuk production; gunakan user aplikasi terbatas.

