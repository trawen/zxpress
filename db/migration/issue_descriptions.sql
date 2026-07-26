-- Magazine issue (table `issue`): body + SEO descriptions, RU/EN.
-- description_* : long text for the issue page
-- meta_description_* : SEO meta (max 256)
ALTER TABLE `issue`
  ADD COLUMN `description_ru` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL AFTER `slug_en`,
  ADD COLUMN `description_en` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL AFTER `description_ru`,
  ADD COLUMN `meta_description_ru` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' AFTER `description_en`,
  ADD COLUMN `meta_description_en` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' AFTER `meta_description_ru`;
