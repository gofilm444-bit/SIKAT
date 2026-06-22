============================================================
PANDUAN MENJALANKAN APLIKASI SI-SKI (SIKAT) DI WINDOWS
============================================================

1. PERSIAPAN
------------
- Pastikan sudah menginstall XAMPP (https://www.apachefriends.org/download.html)
- Pastikan folder backup ini bernama: ski_new
- File/folder utama: dashboard.php, login.php, db/, asset/, style.css, dst.

2. CARA MENJALANKAN
-------------------
A. EKSTRAK FOLDER
   - Ekstrak/copy folder ski_new ke dalam folder htdocs XAMPP:
     Contoh: C:\xampp\htdocs\ski_new

B. JALANKAN XAMPP
   - Buka aplikasi XAMPP Control Panel
   - Start module Apache dan MySQL

C. IMPORT DATABASE
   - Buka browser, akses: http://localhost/phpmyadmin
   - Buat database baru: ski_db
   - Import file db/ski_db_baru.sql (atau ski_db.sql) ke database ski_db

D. AKSES APLIKASI
   - Buka browser, akses: http://localhost/ski_new/login.php
   - Login dengan user default:
     Username: admin
     Password: admin123

3. CATATAN TAMBAHAN
-------------------
- Jika logo tidak muncul, pastikan file asset/logo_polkester.png ada.
- Jika ada error koneksi, cek file db/koneksi.php (user/pass MySQL default: root/tanpa password).
- Untuk backup database, gunakan fitur export di phpMyAdmin.
- Untuk reset data, import ulang file SQL.

4. KONTAK BANTUAN
-----------------
- IT Poltekkes Kemenkes Ternate

5. EARLY WARNING / NOTIFIKASI DEADLINE
--------------------------------------
- Sistem menyediakan skrip CLI untuk mengirim email pengingat tenggat reviu.
- Jalankan secara manual atau jadwalkan via cron: `php cron/early_warning.php`
- Skrip akan mengirim email ketika tenggat tinggal â‰¤2 hari atau sudah terlewat.
- Pastikan tabel `mail_recipients` terisi alamat email admin/penanggung jawab.

============================================================
