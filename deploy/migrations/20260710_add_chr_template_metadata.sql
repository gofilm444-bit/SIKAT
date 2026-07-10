SET @db_name := DATABASE();

CREATE TABLE IF NOT EXISTS reviu_chr_form (
  id INT AUTO_INCREMENT PRIMARY KEY,
  reviu_id INT NOT NULL,
  data_json LONGTEXT NOT NULL,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_reviu (reviu_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @sql := IF(
  (SELECT COUNT(*)
   FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = @db_name
     AND TABLE_NAME = 'reviu_chr_form'
     AND COLUMN_NAME = 'template_code') = 0,
  'ALTER TABLE reviu_chr_form ADD COLUMN template_code VARCHAR(100) NULL AFTER reviu_id',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*)
   FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = @db_name
     AND TABLE_NAME = 'reviu_chr_form'
     AND COLUMN_NAME = 'template_version') = 0,
  'ALTER TABLE reviu_chr_form ADD COLUMN template_version INT NOT NULL DEFAULT 1 AFTER template_code',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*)
   FROM INFORMATION_SCHEMA.STATISTICS
   WHERE TABLE_SCHEMA = @db_name
     AND TABLE_NAME = 'reviu_chr_form'
     AND INDEX_NAME = 'idx_reviu_chr_form_template_code') = 0,
  'CREATE INDEX idx_reviu_chr_form_template_code ON reviu_chr_form (template_code)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
