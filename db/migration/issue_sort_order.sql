-- Ordinal position of an issue within its press.
-- Base value = issue number * 10 (+10 so "00" is not 0), leaving gaps for
-- manual reordering:
--   "07"            -> 80          exact number
--   "4.5"           -> 55          decimal lands between 4 and 5
--   "22-23 promo"   -> 225         range goes before its first number
--   "1A".."1F"      -> 21..26      lettered variants follow their number
--   "A", "actual"   -> after all numbered issues, step 10

SET @has_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'issue'
    AND COLUMN_NAME = 'sort_order'
);
SET @sql := IF(
  @has_col = 0,
  'ALTER TABLE `issue` ADD COLUMN `sort_order` int unsigned NOT NULL DEFAULT 0 AFTER `date`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_idx := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'issue'
    AND INDEX_NAME = 'idx_issue_press_sort'
);
SET @sql := IF(
  @has_idx = 0,
  'ALTER TABLE `issue` ADD KEY `idx_issue_press_sort` (`id_press`, `sort_order`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE `issue` i
INNER JOIN (
  SELECT
    id,
    CASE kind
      WHEN 0 THEN num_base + kind_rank
      WHEN 1 THEN num_base - 5 + kind_rank
      WHEN 2 THEN num_base + 1 + LEAST(kind_rank, 8)
      ELSE tail_start + tail_rank * 10
    END AS sort_order
  FROM (
    SELECT
      id,
      kind,
      num_base,
      COALESCE(MAX(num_base) OVER (PARTITION BY id_press), 0) + 10 AS tail_start,
      ROW_NUMBER() OVER (
        PARTITION BY id_press, kind, num_base
        ORDER BY (date = 0) ASC, date ASC, title ASC, id ASC
      ) - 1 AS kind_rank,
      ROW_NUMBER() OVER (
        PARTITION BY id_press, (kind = 3)
        ORDER BY (date = 0) ASC, date ASC, title ASC, id ASC
      ) - 1 AS tail_rank
    FROM (
      SELECT
        id,
        id_press,
        title,
        date,
        CASE
          WHEN title REGEXP '^[0-9]'
            THEN ROUND(
              CAST(
                CONVERT(REGEXP_SUBSTR(title, '^[0-9]+([.][0-9]+)?') USING utf8mb4)
                AS DECIMAL(12, 4)
              ) * 10
            ) + 10
          ELSE NULL
        END AS num_base,
        CASE
          WHEN title REGEXP '^[0-9]+([.][0-9]+)?$' THEN 0
          WHEN title REGEXP '^[0-9]+[[:space:]]*(-{1,2}|&|/)[[:space:]]*[0-9]+' THEN 1
          WHEN title REGEXP '^[0-9]' THEN 2
          ELSE 3
        END AS kind
      FROM `issue`
    ) parsed
  ) ranked
) x ON x.id = i.id
SET i.sort_order = x.sort_order;
