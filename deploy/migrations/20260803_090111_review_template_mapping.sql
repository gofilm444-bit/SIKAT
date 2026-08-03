SET @db_name := DATABASE();

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE reviu ADD COLUMN nama_kegiatan VARCHAR(255) NULL AFTER kode',
    'SELECT 1'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'reviu' AND COLUMN_NAME = 'nama_kegiatan'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE reviu ADD COLUMN template_code VARCHAR(100) NULL AFTER tgl_deadline',
    'SELECT 1'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'reviu' AND COLUMN_NAME = 'template_code'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE reviu ADD COLUMN template_version INT NOT NULL DEFAULT 1 AFTER template_code',
    'SELECT 1'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'reviu' AND COLUMN_NAME = 'template_version'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'CREATE INDEX idx_reviu_template_code ON reviu (template_code)',
    'SELECT 1'
  )
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'reviu' AND INDEX_NAME = 'idx_reviu_template_code'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE jenis_reviu ADD COLUMN template_code VARCHAR(100) NULL AFTER deskripsi',
    'SELECT 1'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'jenis_reviu' AND COLUMN_NAME = 'template_code'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE jenis_reviu ADD COLUMN template_version INT NOT NULL DEFAULT 1 AFTER template_code',
    'SELECT 1'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'jenis_reviu' AND COLUMN_NAME = 'template_version'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'CREATE INDEX idx_jenis_reviu_template_code ON jenis_reviu (template_code)',
    'SELECT 1'
  )
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'jenis_reviu' AND INDEX_NAME = 'idx_jenis_reviu_template_code'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE jenis_reviu SET template_code='chr_sop', template_version=1
WHERE template_code IS NULL AND LOWER(TRIM(nama)) IN ('reviu standar operasional prosedur', 'reviu sop', 'sop');

UPDATE jenis_reviu SET template_code='chr_rkakl', template_version=1
WHERE template_code IS NULL AND LOWER(TRIM(nama)) IN ('reviu anggaran', 'reviu rka', 'reviu rkakl', 'reviu rka/rkakl', 'reviu rka-k/l', 'rka', 'rkakl', 'rka/rkakl');

UPDATE jenis_reviu SET template_code='chr_rkbmn', template_version=1
WHERE template_code IS NULL AND LOWER(TRIM(nama)) IN ('reviu rkbmn', 'rkbmn');

UPDATE jenis_reviu SET template_code='chr_pipk', template_version=1
WHERE template_code IS NULL AND LOWER(TRIM(nama)) IN ('reviu pipk', 'pipk');

UPDATE jenis_reviu SET template_code='chr_lhkpn_lhkasn', template_version=1
WHERE template_code IS NULL AND LOWER(TRIM(nama)) IN ('reviu lhkpn & lhkasn', 'reviu lhkpn dan lhkasn', 'reviu lhkpn/lhkasn', 'lhkpn', 'lhkasn');

UPDATE jenis_reviu SET template_code='chr_iku_ikt', template_version=1
WHERE template_code IS NULL AND LOWER(TRIM(nama)) IN ('reviu iku-ikt', 'reviu iku/ikt', 'reviu iku', 'reviu ikt', 'iku-ikt', 'iku/ikt', 'iku', 'ikt');

UPDATE jenis_reviu SET template_code='chr_lkj', template_version=1
WHERE template_code IS NULL AND LOWER(TRIM(nama)) IN ('reviu laporan kinerja', 'reviu lkj', 'reviu lakip', 'laporan kinerja', 'lkj', 'lakip');

UPDATE jenis_reviu SET template_code='chr_pengembangan_pegawai', template_version=1
WHERE template_code IS NULL AND LOWER(TRIM(nama)) IN ('reviu pengembangan pegawai', 'pengembangan pegawai', 'reviu pengembangan sdm', 'pengembangan sdm');

UPDATE jenis_reviu SET template_code='chr_manajemen_risiko', template_version=1
WHERE template_code IS NULL AND LOWER(TRIM(nama)) IN ('reviu manajemen risiko', 'reviu manajemen resiko', 'reviu manrisk', 'manajemen risiko', 'manajemen resiko', 'manrisk');

UPDATE jenis_reviu SET template_code='chr_legacy_laporan_keuangan', template_version=1
WHERE template_code IS NULL AND LOWER(TRIM(nama)) IN ('reviu laporan keuangan', 'laporan keuangan');
