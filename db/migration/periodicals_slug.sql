ALTER TABLE `periodicals`
  ADD COLUMN `slug_ru` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `title_en`,
  ADD COLUMN `slug_en` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `slug_ru`,
  ADD UNIQUE KEY `uq_periodicals_slug_ru` (`slug_ru`),
  ADD UNIQUE KEY `uq_periodicals_slug_en` (`slug_en`);

ALTER TABLE `periodical_issues`
  ADD COLUMN `slug_ru` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `title_en`,
  ADD COLUMN `slug_en` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `slug_ru`,
  ADD UNIQUE KEY `uq_periodical_issue_slug_ru` (`periodical_id`, `slug_ru`),
  ADD UNIQUE KEY `uq_periodical_issue_slug_en` (`periodical_id`, `slug_en`);

ALTER TABLE `periodical_articles`
  ADD COLUMN `slug_ru` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `title_en`,
  ADD COLUMN `slug_en` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `slug_ru`,
  ADD UNIQUE KEY `uq_periodical_article_slug_ru` (`issue_id`, `slug_ru`),
  ADD UNIQUE KEY `uq_periodical_article_slug_en` (`issue_id`, `slug_en`);
