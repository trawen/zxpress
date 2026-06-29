ALTER TABLE `publishers`
  ADD COLUMN `meta_description_ru` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' AFTER `description_en`,
  ADD COLUMN `meta_description_en` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' AFTER `meta_description_ru`;
