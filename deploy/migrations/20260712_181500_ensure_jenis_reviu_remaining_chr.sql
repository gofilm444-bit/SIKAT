INSERT INTO jenis_reviu (nama, deskripsi, aktif)
SELECT 'Reviu Pengembangan Pegawai', 'Reviu atas proses dan dokumen pengembangan pegawai', 1
WHERE NOT EXISTS (SELECT 1 FROM jenis_reviu WHERE nama = 'Reviu Pengembangan Pegawai');

INSERT INTO jenis_reviu (nama, deskripsi, aktif)
SELECT 'Reviu LHKPN dan LHKASN', 'Reviu kepatuhan pelaporan LHKPN dan LHKASN', 1
WHERE NOT EXISTS (SELECT 1 FROM jenis_reviu WHERE nama IN ('Reviu LHKPN dan LHKASN', 'Reviu LHKPN & LHKASN', 'Reviu LHKPN/LHKASN'));

INSERT INTO jenis_reviu (nama, deskripsi, aktif)
SELECT 'Reviu IKU-IKT', 'Reviu indikator kinerja utama dan indikator kinerja tambahan', 1
WHERE NOT EXISTS (SELECT 1 FROM jenis_reviu WHERE nama IN ('Reviu IKU-IKT', 'Reviu IKU/IKT'));

INSERT INTO jenis_reviu (nama, deskripsi, aktif)
SELECT 'Reviu Laporan Kinerja', 'Reviu laporan kinerja/LKJ satuan kerja atau unit kerja', 1
WHERE NOT EXISTS (SELECT 1 FROM jenis_reviu WHERE nama IN ('Reviu Laporan Kinerja', 'Reviu LKJ', 'Reviu LAKIP'));

INSERT INTO jenis_reviu (nama, deskripsi, aktif)
SELECT 'Reviu PIPK', 'Reviu pengendalian intern atas pelaporan keuangan tingkat satker', 1
WHERE NOT EXISTS (SELECT 1 FROM jenis_reviu WHERE nama IN ('Reviu PIPK', 'PIPK'));

INSERT INTO jenis_reviu (nama, deskripsi, aktif)
SELECT 'Reviu RKBMN', 'Reviu rencana kebutuhan barang milik negara', 1
WHERE NOT EXISTS (SELECT 1 FROM jenis_reviu WHERE nama IN ('Reviu RKBMN', 'RKBMN'));
