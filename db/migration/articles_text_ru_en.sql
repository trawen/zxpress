-- Magazine articles (table `articles`): body text in DB, RU/EN.
-- Intended to replace file storage under content-store/articles{,-eng}/{id}.
ALTER TABLE `articles`
  ADD COLUMN `text_ru` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL AFTER `text_type`,
  ADD COLUMN `text_en` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL AFTER `text_ru`;
