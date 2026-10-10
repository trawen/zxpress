-- Colophon date "Подписано в печать" on an edition.
SET NAMES utf8mb4;

SET @has_col := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'book_editions'
    AND COLUMN_NAME = 'signed_for_print'
);

SET @sql := IF(
  @has_col = 0,
  'ALTER TABLE `book_editions` ADD COLUMN `signed_for_print` DATE NULL DEFAULT NULL AFTER `publish_date`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
