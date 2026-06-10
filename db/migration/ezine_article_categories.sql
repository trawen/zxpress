CREATE TABLE `ezine_article_categories` (
  `category_id` bigint unsigned NOT NULL,
  `article_id` int NOT NULL,
  `sort_order` tinyint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`category_id`,`article_id`),
  KEY `fk_eac_article` (`article_id`),
  CONSTRAINT `fk_eac_article` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_eac_category` FOREIGN KEY (`category_id`) REFERENCES `ezine_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
