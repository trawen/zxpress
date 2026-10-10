-- Add language codes for books_v2 i18n (and future locale tables).
SET NAMES utf8mb4;

SET @has_code := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'languages'
    AND COLUMN_NAME = 'code'
);

SET @sql := IF(
  @has_code = 0,
  'ALTER TABLE `languages`
     ADD COLUMN `code` CHAR(2) NULL AFTER `name`,
     ADD COLUMN `is_site_locale` TINYINT(1) NOT NULL DEFAULT 0 AFTER `code`,
     ADD COLUMN `sort_order` INT NOT NULL DEFAULT 0 AFTER `is_site_locale`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE languages SET code = 'ru', is_site_locale = 1, sort_order = 10 WHERE id = 1 AND (code IS NULL OR code = '');
UPDATE languages SET code = 'en', is_site_locale = 1, sort_order = 20 WHERE id = 2 AND (code IS NULL OR code = '');
UPDATE languages SET code = 'de', is_site_locale = 0, sort_order = 30 WHERE id = 3 AND (code IS NULL OR code = '');
UPDATE languages SET code = 'es', is_site_locale = 0, sort_order = 40 WHERE id = 4 AND (code IS NULL OR code = '');

-- Fill any remaining rows with a placeholder code based on id (should not happen).
UPDATE languages
SET code = LOWER(LEFT(CONCAT('x', id), 2)), is_site_locale = 0, sort_order = id * 10
WHERE code IS NULL OR code = '';

ALTER TABLE `languages`
  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `languages`
  MODIFY COLUMN `code` CHAR(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;

SET @has_uq := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'languages'
    AND INDEX_NAME = 'uq_languages_code'
);
SET @sql := IF(
  @has_uq = 0,
  'ALTER TABLE `languages` ADD UNIQUE KEY `uq_languages_code` (`code`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
