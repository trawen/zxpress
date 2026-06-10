CREATE TABLE `ezine_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name_ru` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_en` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title_ru` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `title_en` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `description_ru` text COLLATE utf8mb4_unicode_ci,
  `description_en` text COLLATE utf8mb4_unicode_ci,
  `meta_description_ru` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_description_en` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `parent_id` bigint unsigned DEFAULT NULL,
  `sort_order` tinyint unsigned NOT NULL DEFAULT '0',
  `articles_count` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_category_parent` (`parent_id`),
  CONSTRAINT `fk_category_parent` FOREIGN KEY (`parent_id`) REFERENCES `ezine_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=177 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
