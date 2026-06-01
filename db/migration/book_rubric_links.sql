CREATE TABLE `book_rubric_links` (
    `book_id`   INT NOT NULL,
    `rubric_id` INT NOT NULL,
    PRIMARY KEY (`book_id`, `rubric_id`),
    KEY `idx_rubric_id` (`rubric_id`)
) ENGINE=MyISAM
  DEFAULT CHARSET=utf8mb3
  COLLATE=utf8mb3_unicode_ci
  COMMENT='Связь книг и рубрик';
