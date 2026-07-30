-- Article body format for DB-stored text (legacy 0 kept for old rows).
-- 0=legacy/file (treat as html pre), 1=text pre, 2=html pre (default for new), 3=markdown
ALTER TABLE `articles`
  MODIFY COLUMN `text_type` int NOT NULL DEFAULT 2 COMMENT '0=legacy,1=text_pre,2=html_pre,3=markdown';
