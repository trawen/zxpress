ALTER TABLE `ezine_categories`
  ADD COLUMN `title_ru` varchar(255) NOT NULL DEFAULT '' AFTER `name_en`,
  ADD COLUMN `title_en` varchar(255) NOT NULL DEFAULT '' AFTER `title_ru`;
