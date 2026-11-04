-- Imprint is multiline Markdown, not a short string.
SET NAMES utf8mb4;

ALTER TABLE `book_work_i18n`
  MODIFY `imprint` TEXT NULL;
