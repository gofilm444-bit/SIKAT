CREATE TABLE IF NOT EXISTS unit_kerja (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(191) NOT NULL,
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE pengguna
  ADD COLUMN IF NOT EXISTS nip VARCHAR(30) NULL AFTER nama,
  ADD COLUMN IF NOT EXISTS jabatan VARCHAR(200) NULL AFTER nip,
  ADD COLUMN IF NOT EXISTS unit_id INT NULL AFTER jabatan;

UPDATE pengguna p
LEFT JOIN unit_kerja u ON u.id = p.unit_id
SET p.unit_id = NULL
WHERE p.unit_id IS NOT NULL
  AND u.id IS NULL;

SET @idx_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pengguna'
    AND INDEX_NAME = 'idx_pengguna_unit_id'
);

SET @sql := IF(
  @idx_exists = 0,
  'ALTER TABLE pengguna ADD INDEX idx_pengguna_unit_id (unit_id)',
  'SELECT ''idx_pengguna_unit_id already exists'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pengguna'
    AND CONSTRAINT_NAME = 'fk_pengguna_unit_kerja'
);

SET @invalid_unit_rows := (
  SELECT COUNT(*)
  FROM pengguna p
  LEFT JOIN unit_kerja u ON u.id = p.unit_id
  WHERE p.unit_id IS NOT NULL
    AND u.id IS NULL
);

SET @sql := IF(
  @fk_exists = 0 AND @invalid_unit_rows = 0,
  'ALTER TABLE pengguna ADD CONSTRAINT fk_pengguna_unit_kerja FOREIGN KEY (unit_id) REFERENCES unit_kerja(id) ON UPDATE CASCADE ON DELETE SET NULL',
  'SELECT ''fk_pengguna_unit_kerja skipped or already exists'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
