-- Author URL slugs (ASCII, unique per language).
ALTER TABLE `authors`
  ADD COLUMN `slug_ru` varchar(191) CHARACTER SET ascii COLLATE ascii_general_ci NULL DEFAULT NULL AFTER `group_name`,
  ADD COLUMN `slug_en` varchar(191) CHARACTER SET ascii COLLATE ascii_general_ci NULL DEFAULT NULL AFTER `slug_ru`,
  ADD UNIQUE KEY `uq_authors_slug_ru` (`slug_ru`),
  ADD UNIQUE KEY `uq_authors_slug_en` (`slug_en`);
