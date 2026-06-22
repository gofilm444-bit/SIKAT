# Security Sweep Batch #3 Report

Tanggal: 2026-01-26

## Ringkasan Fokus
Batch ini mengunci akses lampiran agar tidak bisa diakses langsung via URL statis dan memastikan semua download melewati gateway dengan guard auth + role.

## Hasil Discovery
### Endpoints terkait lampiran
- `login.php` (pelacakan publik) menampilkan lampiran `pelaporan_files` dengan link langsung `rel_path`.
- `pelaporan_detail.php` menampilkan lampiran pelaporan dengan link langsung `rel_path`.
- `download.php` melayani dokumen reviu (`reviu_dokumen`) via id dengan guard assignment.

### Lokasi penyimpanan lampiran
- `uploads/` (tanggal folder), `uploads/rekap/`, `uploads/reviu/`
- `upload/` (tersedia, namun belum terlihat dipakai langsung)

### Model akses sebelumnya
- Lampiran pelaporan dapat diakses langsung via URL `uploads/...` tanpa guard session/role.
- Dokumen reviu sudah melalui `download.php` (guarded).

### Risiko
- IDOR/akses tidak sah terhadap lampiran pelaporan via URL statis.
- Bypass kontrol akses berbasis role.
- Potensi path traversal jika path dimanipulasi di storage (tanpa validasi containment).

## Perubahan Batch #3
- Menambahkan gateway `attachment_download.php` untuk semua lampiran pelaporan (guard login + role + status).
- Mengalihkan link lampiran di `login.php` dan `pelaporan_detail.php` ke gateway.
- Menambahkan validasi path (realpath + allowed base dir) sebelum streaming file.
- Memblokir akses langsung ke `/uploads/` dan `/upload/` via `.htaccess` dan Nginx snippet.

## Catatan Akses
- Admin/kepala_ski/direktur dapat mengakses lampiran pelaporan sesuai status yang terlihat.
- User tanpa login akan diarahkan ke login/ditolak.

## Sisa Risiko (Jika Ada)
- Lampiran masih tersimpan di webroot; mitigasi utama mengandalkan gateway + deny direct access.
