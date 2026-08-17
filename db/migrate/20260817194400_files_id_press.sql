-- Files may belong to a whole press (id_issue=0) instead of one issue.
-- Used when a disk/archive is shared by every number of the magazine.

SET @has_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'files'
    AND COLUMN_NAME = 'id_press'
);
SET @sql := IF(
  @has_col = 0,
  'ALTER TABLE `files` ADD COLUMN `id_press` int NOT NULL DEFAULT 0 AFTER `id_issue`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE `files` f
INNER JOIN `issue` i ON i.id = f.id_issue
SET f.id_press = i.id_press
WHERE f.id_press = 0 AND f.id_issue > 0;

SET @has_idx := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'files'
    AND INDEX_NAME = 'idx_files_press_issue'
);
SET @sql := IF(
  @has_idx = 0,
  'ALTER TABLE `files` ADD KEY `idx_files_press_issue` (`id_press`, `id_issue`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
