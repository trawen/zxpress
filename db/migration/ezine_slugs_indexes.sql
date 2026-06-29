ALTER TABLE `issue`
  ADD UNIQUE KEY `uq_issue_press_slug_ru` (`id_press`, `slug_ru`),
  ADD UNIQUE KEY `uq_issue_press_slug_en` (`id_press`, `slug_en`);

ALTER TABLE `articles`
  ADD UNIQUE KEY `uq_articles_issue_slug_ru` (`id_issue`, `slug_ru`),
  ADD UNIQUE KEY `uq_articles_issue_slug_en` (`id_issue`, `slug_en`);
