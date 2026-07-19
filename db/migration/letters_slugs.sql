ALTER TABLE `letters`
  ADD COLUMN `slug_ru` varchar(191) CHARACTER SET ascii COLLATE ascii_general_ci NULL DEFAULT NULL AFTER `title_en`,
  ADD COLUMN `slug_en` varchar(191) CHARACTER SET ascii COLLATE ascii_general_ci NULL DEFAULT NULL AFTER `slug_ru`;
