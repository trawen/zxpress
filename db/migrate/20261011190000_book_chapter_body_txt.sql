-- Book chapter body can be stored as HTML, Markdown, or plain text.
SET NAMES utf8mb4;

ALTER TABLE `book_chapter_bodies`
  MODIFY `format` ENUM('html','markdown','txt') NOT NULL DEFAULT 'html';
