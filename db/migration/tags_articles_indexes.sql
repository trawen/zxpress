-- Hot path: tag.php / book_articles.php / article.php tag lookups
-- Without these, tags_articles is full-scanned (~220k rows) on every tag page hit.
CREATE INDEX idx_tags_articles_id_tag ON tags_articles (id_tag, id_article);
CREATE INDEX idx_tags_articles_article_type ON tags_articles (id_article, tag_type);
