ALTER TABLE `echos_zxnet`
  ADD COLUMN `name_from_en` varchar(32) NULL AFTER `name_from`,
  ADD COLUMN `name_to_en` varchar(32) NULL AFTER `name_to`;
