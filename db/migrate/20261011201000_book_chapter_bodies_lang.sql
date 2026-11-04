SET NAMES utf8mb4;

SET @has_lang := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'book_chapter_bodies'
    AND COLUMN_NAME = 'lang'
);

SET @sql := IF(
  @has_lang = 0,
  'ALTER TABLE `book_chapter_bodies` ADD COLUMN `lang` CHAR(2) NOT NULL DEFAULT ''ru'' AFTER `chapter_id`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE `book_chapter_bodies` b
INNER JOIN (
  SELECT `chapter_id`, MIN(`lang`) AS `lang`
  FROM `book_chapter_i18n`
  GROUP BY `chapter_id`
  HAVING COUNT(*) = 1
) i ON i.`chapter_id` = b.`chapter_id`
SET b.`lang` = i.`lang`
WHERE b.`lang` = 'ru' AND i.`lang` <> 'ru';

SET @pk := (
  SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'book_chapter_bodies'
    AND INDEX_NAME = 'PRIMARY'
);

SET @sql := IF(
  @pk = 'chapter_id',
  'ALTER TABLE `book_chapter_bodies` DROP PRIMARY KEY, ADD PRIMARY KEY (`chapter_id`, `lang`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_fk := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'book_chapter_bodies'
    AND CONSTRAINT_NAME = 'fk_book_chapter_bodies_lang'
);

SET @sql := IF(
  @has_fk = 0,
  'ALTER TABLE `book_chapter_bodies` ADD CONSTRAINT `fk_book_chapter_bodies_lang` FOREIGN KEY (`lang`) REFERENCES `languages` (`code`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
