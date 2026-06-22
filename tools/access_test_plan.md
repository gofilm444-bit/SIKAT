# Access Control Test Plan

1) Buka `http://localhost/ski_new/` tanpa login -> harus redirect ke `login.php`.
2) Buka langsung halaman admin (mis `pengguna.php`) tanpa login -> harus redirect ke `login.php`.
3) Login sebagai user biasa -> akses `pengguna.php` harus 403.
4) Login sebagai admin/superadmin -> akses `pengguna.php` harus berhasil.
5) Logout -> semua halaman kembali redirect ke `login.php`.
