ALTER TABLE `periodicals`
  ADD COLUMN `is_samizdat` tinyint(1) NOT NULL DEFAULT '0' AFTER `is_active`;
