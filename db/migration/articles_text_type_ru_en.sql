-- Per-language article body format (RU / EN may differ).
-- 0=legacy, 1=text_pre, 2=html_pre, 3=markdown
-- Backfills once from existing text_type; keeps text_type as RU-compatible mirror.

SET @has_ru := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'articles'
    AND COLUMN_NAME = 'text_type_ru'
);
SET @has_en := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'articles'
    AND COLUMN_NAME = 'text_type_en'
);

SET @sql := IF(
  @has_ru = 0,
  'ALTER TABLE `articles` ADD COLUMN `text_type_ru` int NOT NULL DEFAULT 2 COMMENT ''0=legacy,1=text_pre,2=html_pre,3=markdown'' AFTER `text_type`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  @has_en = 0,
  'ALTER TABLE `articles` ADD COLUMN `text_type_en` int NOT NULL DEFAULT 2 COMMENT ''0=legacy,1=text_pre,2=html_pre,3=markdown'' AFTER `text_type_ru`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- One-shot backfill only when at least one column was just created.
SET @do_backfill := IF(@has_ru = 0 OR @has_en = 0, 1, 0);
SET @sql := IF(
  @do_backfill = 1,
  'UPDATE `articles` SET
    `text_type_ru` = CASE WHEN `text_type` IN (1, 2, 3) THEN `text_type` ELSE 2 END,
    `text_type_en` = CASE WHEN `text_type` IN (1, 2, 3) THEN `text_type` ELSE 2 END',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
