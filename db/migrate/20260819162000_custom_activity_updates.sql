CREATE TABLE IF NOT EXISTS `custom_activity_updates` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `created_at` INT UNSIGNED NOT NULL,
  `created_by` INT UNSIGNED NOT NULL DEFAULT 0,
  `title_ru` VARCHAR(255) NOT NULL DEFAULT '',
  `title_en` VARCHAR(255) NOT NULL DEFAULT '',
  `image_url` VARCHAR(512) NOT NULL DEFAULT '',
  `image_width` INT UNSIGNED NOT NULL DEFAULT 0,
  `image_height` INT UNSIGNED NOT NULL DEFAULT 0,
  `activity_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_custom_activity_updates_created_at` (`created_at`, `id`),
  KEY `idx_custom_activity_updates_activity_id` (`activity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
