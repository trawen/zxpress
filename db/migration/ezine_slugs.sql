-- press.slug_* may already exist from a partial apply; skip if present.

ALTER TABLE `issue`
  ADD COLUMN `slug_ru` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' AFTER `views`,
  ADD COLUMN `slug_en` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' AFTER `slug_ru`;

ALTER TABLE `articles`
  ADD COLUMN `slug_ru` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' AFTER `meta_description_en`,
  ADD COLUMN `slug_en` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' AFTER `slug_ru`;
