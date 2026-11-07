ALTER TABLE `screens`
  ADD COLUMN `width` smallint unsigned DEFAULT NULL AFTER `format`,
  ADD COLUMN `height` smallint unsigned DEFAULT NULL AFTER `width`;
