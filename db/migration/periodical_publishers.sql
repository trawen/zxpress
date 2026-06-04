CREATE TABLE `periodical_publishers` (
  `periodical_id` int unsigned NOT NULL,
  `publisher_id` int unsigned NOT NULL,
  PRIMARY KEY (`periodical_id`,`publisher_id`),
  KEY `publisher_id` (`publisher_id`),
  CONSTRAINT `periodical_publishers_ibfk_1` FOREIGN KEY (`periodical_id`) REFERENCES `periodicals` (`id`) ON DELETE CASCADE,
  CONSTRAINT `periodical_publishers_ibfk_2` FOREIGN KEY (`publisher_id`) REFERENCES `publishers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
