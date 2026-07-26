ALTER TABLE `echos_subjs2`
  ADD COLUMN `slug_ru` varchar(128) NULL AFTER `title_en`,
  ADD COLUMN `slug_en` varchar(128) NULL AFTER `slug_ru`,
  ADD UNIQUE KEY `echos_subjs2_echo_slug_ru` (`echo_id`, `slug_ru`),
  ADD UNIQUE KEY `echos_subjs2_echo_slug_en` (`echo_id`, `slug_en`);
