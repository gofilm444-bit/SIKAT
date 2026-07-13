INSERT INTO jenis_reviu (nama, deskripsi, aktif)
SELECT
    'Reviu Standar Operasional Prosedur',
    'Reviu terhadap ketersediaan, format, dan pelaksanaan Standar Operasional Prosedur',
    1
WHERE NOT EXISTS (
    SELECT 1
    FROM jenis_reviu
    WHERE LOWER(TRIM(nama)) IN (
        'reviu standar operasional prosedur',
        'reviu sop',
        'sop'
    )
);
