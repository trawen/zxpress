-- Align ezine_issue_i18n.meta_description with legacy issue.meta_description_* (VARCHAR(512)).
ALTER TABLE `ezine_issue_i18n`
  MODIFY COLUMN `meta_description` VARCHAR(512) NOT NULL DEFAULT '';
