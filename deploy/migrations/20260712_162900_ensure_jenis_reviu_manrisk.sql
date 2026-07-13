UPDATE jenis_reviu
SET
  nama = 'Reviu Manajemen Risiko',
  deskripsi = COALESCE(NULLIF(deskripsi, ''), 'Reviu terhadap penerapan dan pemantauan manajemen risiko unit kerja'),
  aktif = 1
WHERE LOWER(TRIM(nama)) IN (
  'reviu manajemen resiko',
  'reviu manajemen risiko',
  'manajemen resiko',
  'manajemen risiko',
  'manrisk',
  'reviu manrisk'
);

INSERT INTO jenis_reviu (nama, deskripsi, aktif)
SELECT
  'Reviu Manajemen Risiko',
  'Reviu terhadap penerapan dan pemantauan manajemen risiko unit kerja',
  1
WHERE NOT EXISTS (
  SELECT 1
  FROM jenis_reviu
  WHERE LOWER(TRIM(nama)) IN (
    'reviu manajemen risiko',
    'reviu manajemen resiko',
    'reviu manrisk',
    'manrisk'
  )
);
