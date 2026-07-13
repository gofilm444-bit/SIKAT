UPDATE jenis_reviu
SET
  nama = 'Reviu RKA/RKAKL',
  deskripsi = COALESCE(NULLIF(deskripsi, ''), 'Reviu atas RKA-K/L dan RKAKL berdasarkan format catatan hasil reviu RKA'),
  aktif = 1
WHERE LOWER(TRIM(nama)) IN (
  'reviu anggaran',
  'reviu rka',
  'reviu rkakl',
  'rka',
  'rkakl',
  'rka/rkakl',
  'rka-k/l',
  'reviu rka-k/l'
);

INSERT INTO jenis_reviu (nama, deskripsi, aktif)
SELECT
  'Reviu RKA/RKAKL',
  'Reviu atas RKA-K/L dan RKAKL berdasarkan format catatan hasil reviu RKA',
  1
WHERE NOT EXISTS (
  SELECT 1
  FROM jenis_reviu
  WHERE LOWER(TRIM(nama)) IN (
    'reviu rka/rkakl',
    'reviu rka-k/l',
    'reviu rkakl',
    'reviu rka'
  )
);
