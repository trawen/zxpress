-- Allow meta_description up to 1024 chars (issue/press/article + v2 i18n).
ALTER TABLE `ezine_issue_i18n`
  MODIFY COLUMN `meta_description` VARCHAR(1024) NOT NULL DEFAULT '';

ALTER TABLE `issue`
  MODIFY COLUMN `meta_description_ru` VARCHAR(1024) NOT NULL DEFAULT '',
  MODIFY COLUMN `meta_description_en` VARCHAR(1024) NOT NULL DEFAULT '';

ALTER TABLE `ezine_i18n`
  MODIFY COLUMN `meta_description` VARCHAR(1024) NOT NULL DEFAULT '';

ALTER TABLE `press`
  MODIFY COLUMN `meta_description_ru` VARCHAR(1024) NOT NULL DEFAULT '',
  MODIFY COLUMN `meta_description_en` VARCHAR(1024) NOT NULL DEFAULT '';

ALTER TABLE `ezine_article_i18n`
  MODIFY COLUMN `meta_description` VARCHAR(1024) NOT NULL DEFAULT '';
