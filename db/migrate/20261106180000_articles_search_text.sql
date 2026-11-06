-- Plain body prepared for Manticore fulltext (see site/includes/article_search_text.php).
ALTER TABLE `articles`
  ADD COLUMN `search_text` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL
  AFTER `text_en`;
