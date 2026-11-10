-- Allow meta_description up to 1024 chars (issue/press/article + v2 i18n).
-- Keep utf8mb4 explicitly: legacy `issue`/`press` tables are still cp1251 by default.
ALTER TABLE `ezine_issue_i18n`
  MODIFY COLUMN `meta_description` VARCHAR(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '';

ALTER TABLE `issue`
  MODIFY COLUMN `meta_description_ru` VARCHAR(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  MODIFY COLUMN `meta_description_en` VARCHAR(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '';

ALTER TABLE `ezine_i18n`
  MODIFY COLUMN `meta_description` VARCHAR(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '';

ALTER TABLE `press`
  MODIFY COLUMN `meta_description_ru` VARCHAR(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  MODIFY COLUMN `meta_description_en` VARCHAR(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '';

ALTER TABLE `ezine_article_i18n`
  MODIFY COLUMN `meta_description` VARCHAR(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '';
