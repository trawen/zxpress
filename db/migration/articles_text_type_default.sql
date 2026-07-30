-- Ensure articles.text_type defaults to 0 (= file storage).
-- text_ru / text_en already added in articles_text_ru_en.sql.
ALTER TABLE `articles`
  MODIFY COLUMN `text_type` int NOT NULL DEFAULT 0 COMMENT '0=file';
