SET @has_col := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'issue'
    AND COLUMN_NAME = 'missing'
);

SET @sql := IF(
  @has_col = 0,
  'ALTER TABLE `issue` ADD COLUMN `missing` TINYINT(1) NOT NULL DEFAULT 0 AFTER `views`',
  'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
