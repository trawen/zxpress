-- Multilingual imprint (publisher brand) on book works.
SET NAMES utf8mb4;

SET @has_col := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'book_work_i18n'
    AND COLUMN_NAME = 'imprint'
);
SET @sql := IF(
  @has_col = 0,
  'ALTER TABLE `book_work_i18n`
     ADD COLUMN `imprint` TEXT NULL AFTER `subtitle`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
