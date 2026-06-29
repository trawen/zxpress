-- Remove mistaken single `slug` column and composite indexes from initial URL rollout.
ALTER TABLE `periodicals` DROP INDEX `uq_periodicals_slug`, DROP COLUMN `slug`;
ALTER TABLE `periodical_issues` DROP INDEX `uq_periodical_issue_slug`, DROP COLUMN `slug`;
ALTER TABLE `periodical_articles` DROP INDEX `uq_periodical_article_slug`, DROP COLUMN `slug`;
