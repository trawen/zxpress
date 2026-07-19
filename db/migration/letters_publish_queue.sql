-- Soft statuses + auto-publish queue for snailmail letters.
-- Status: 0=draft, 1=queued, 2=published, 3=deleted

ALTER TABLE `letters`
  ADD COLUMN `publish_status` TINYINT UNSIGNED NOT NULL DEFAULT 2 AFTER `is_active`,
  ADD COLUMN `queued_at` DATETIME NULL DEFAULT NULL AFTER `publish_status`,
  ADD COLUMN `published_at` DATETIME NULL DEFAULT NULL AFTER `queued_at`,
  ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL AFTER `published_at`,
  ADD KEY `idx_letters_publish_queue` (`publish_status`, `queued_at`, `id`),
  ADD KEY `idx_letters_published_at` (`published_at`);

-- Backfill existing rows from is_active.
UPDATE `letters`
SET
  `publish_status` = CASE WHEN `is_active` = 1 THEN 2 ELSE 0 END,
  `published_at` = CASE WHEN `is_active` = 1 THEN COALESCE(`created_at`, NOW()) ELSE NULL END,
  `queued_at` = NULL,
  `deleted_at` = NULL;
