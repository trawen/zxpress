-- Plain body prepared for Manticore fulltext (see site/includes/article_search_text.php).
-- Idempotent for manual apply on existing DBs.
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'articles'
    AND COLUMN_NAME = 'search_text'
);
SET @sql := IF(
  @col_exists = 0,
  'ALTER TABLE `articles` ADD COLUMN `search_text` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL AFTER `text_en`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
