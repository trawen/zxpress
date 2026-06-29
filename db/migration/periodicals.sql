CREATE TABLE `periodicals` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `title_ru` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title_en` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `slug_ru` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `slug_en` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `issn` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `city_id` int NOT NULL DEFAULT '1',
  `description_ru` text COLLATE utf8mb4_unicode_ci,
  `description_en` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_samizdat` tinyint(1) NOT NULL DEFAULT '0',
  `year_start` smallint unsigned DEFAULT NULL,
  `year_end` smallint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_periodicals_slug_ru` (`slug_ru`),
  UNIQUE KEY `uq_periodicals_slug_en` (`slug_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

