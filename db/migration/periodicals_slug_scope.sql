ALTER TABLE `periodical_issues`
  DROP INDEX `periodical_issues_slug_ru_unique`,
  DROP INDEX `periodical_issues_slug_en_unique`,
  ADD UNIQUE KEY `uq_periodical_issue_slug_ru` (`periodical_id`, `slug_ru`),
  ADD UNIQUE KEY `uq_periodical_issue_slug_en` (`periodical_id`, `slug_en`);

ALTER TABLE `periodical_articles`
  DROP INDEX `periodical_articles_slug_ru_unique`,
  DROP INDEX `periodical_articles_slug_en_unique`,
  ADD UNIQUE KEY `uq_periodical_article_slug_ru` (`issue_id`, `slug_ru`),
  ADD UNIQUE KEY `uq_periodical_article_slug_en` (`issue_id`, `slug_en`);
