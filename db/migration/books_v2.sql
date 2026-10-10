-- Books v2: works / editions / chapters (normalized, i18n).
-- Requires languages.code (languages_code.sql).
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `book_works` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `original_language_id` INT NOT NULL,
  `first_year` SMALLINT NULL DEFAULT NULL,
  `primary_edition_id` INT UNSIGNED NULL DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `legacy_book_id` INT UNSIGNED NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_book_works_legacy` (`legacy_book_id`),
  KEY `idx_book_works_active` (`is_active`),
  CONSTRAINT `fk_book_works_lang`
    FOREIGN KEY (`original_language_id`) REFERENCES `languages` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `book_work_i18n` (
  `work_id` INT UNSIGNED NOT NULL,
  `lang` CHAR(2) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `subtitle` VARCHAR(255) NOT NULL DEFAULT '',
  `slug` VARCHAR(191) NOT NULL,
  `annotation` TEXT NULL,
  `meta_description` VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`work_id`, `lang`),
  UNIQUE KEY `uq_book_work_i18n_slug` (`lang`, `slug`),
  CONSTRAINT `fk_book_work_i18n_work`
    FOREIGN KEY (`work_id`) REFERENCES `book_works` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_book_work_i18n_lang`
    FOREIGN KEY (`lang`) REFERENCES `languages` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `book_editions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `work_id` INT UNSIGNED NOT NULL,
  `edition_kind` ENUM('original','reprint','revised','translation','facsimile','electronic') NOT NULL DEFAULT 'original',
  `based_on_edition_id` INT UNSIGNED NULL DEFAULT NULL,
  `content_edition_id` INT UNSIGNED NULL DEFAULT NULL,
  `language_id` INT NOT NULL,
  `edition_number` SMALLINT NULL DEFAULT NULL,
  `publish_year` SMALLINT NULL DEFAULT NULL,
  `publish_date` DATE NULL DEFAULT NULL,
  `signed_for_print` DATE NULL DEFAULT NULL,
  `city_id` INT NULL DEFAULT NULL,
  `pages` INT NULL DEFAULT NULL,
  `circulation` INT NULL DEFAULT NULL,
  `format` VARCHAR(32) NOT NULL DEFAULT '',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `legacy_book_id` INT UNSIGNED NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_book_editions_work` (`work_id`, `sort_order`, `id`),
  KEY `idx_book_editions_legacy` (`legacy_book_id`),
  CONSTRAINT `fk_book_editions_work`
    FOREIGN KEY (`work_id`) REFERENCES `book_works` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_book_editions_lang`
    FOREIGN KEY (`language_id`) REFERENCES `languages` (`id`),
  CONSTRAINT `fk_book_editions_based`
    FOREIGN KEY (`based_on_edition_id`) REFERENCES `book_editions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_book_editions_content`
    FOREIGN KEY (`content_edition_id`) REFERENCES `book_editions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_book_editions_city`
    FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Late FK: primary edition of a work.
SET @has_fk := (
  SELECT COUNT(*)
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'book_works'
    AND CONSTRAINT_NAME = 'fk_book_works_primary_edition'
);
SET @sql := IF(
  @has_fk = 0,
  'ALTER TABLE `book_works`
     ADD CONSTRAINT `fk_book_works_primary_edition`
     FOREIGN KEY (`primary_edition_id`) REFERENCES `book_editions` (`id`) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS `book_edition_i18n` (
  `edition_id` INT UNSIGNED NOT NULL,
  `lang` CHAR(2) NOT NULL,
  `slug` VARCHAR(191) NOT NULL DEFAULT '',
  `title_override` VARCHAR(255) NOT NULL DEFAULT '',
  `edition_statement` VARCHAR(255) NOT NULL DEFAULT '',
  `note` TEXT NULL,
  `meta_description` VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`edition_id`, `lang`),
  CONSTRAINT `fk_book_edition_i18n_edition`
    FOREIGN KEY (`edition_id`) REFERENCES `book_editions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_book_edition_i18n_lang`
    FOREIGN KEY (`lang`) REFERENCES `languages` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `book_edition_isbns` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `edition_id` INT UNSIGNED NOT NULL,
  `isbn_raw` VARCHAR(32) NOT NULL,
  `isbn13` CHAR(13) NULL DEFAULT NULL,
  `binding` ENUM('hardcover','paperback','other') NULL DEFAULT NULL,
  `publisher_id` INT UNSIGNED NULL DEFAULT NULL,
  `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
  `is_invalid` TINYINT(1) NOT NULL DEFAULT 0,
  `sort_order` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_book_edition_isbns_edition` (`edition_id`, `sort_order`, `id`),
  KEY `idx_book_edition_isbns_isbn13` (`isbn13`),
  CONSTRAINT `fk_book_edition_isbns_edition`
    FOREIGN KEY (`edition_id`) REFERENCES `book_editions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_book_edition_isbns_publisher`
    FOREIGN KEY (`publisher_id`) REFERENCES `publishers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `book_edition_printings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `edition_id` INT UNSIGNED NOT NULL,
  `printing_no` SMALLINT NOT NULL DEFAULT 1,
  `year` SMALLINT NULL DEFAULT NULL,
  `circulation` INT NULL DEFAULT NULL,
  `note` VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_book_edition_printings` (`edition_id`, `printing_no`),
  CONSTRAINT `fk_book_edition_printings_edition`
    FOREIGN KEY (`edition_id`) REFERENCES `book_editions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `book_credits` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `work_id` INT UNSIGNED NULL DEFAULT NULL,
  `edition_id` INT UNSIGNED NULL DEFAULT NULL,
  `author_id` INT NOT NULL,
  `role` ENUM('author','coauthor','compiler','editor','translator','illustrator','designer','foreword') NOT NULL DEFAULT 'author',
  `sort_order` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_book_credits_work` (`work_id`, `sort_order`, `id`),
  KEY `idx_book_credits_edition` (`edition_id`, `sort_order`, `id`),
  KEY `idx_book_credits_author` (`author_id`),
  CONSTRAINT `fk_book_credits_work`
    FOREIGN KEY (`work_id`) REFERENCES `book_works` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_book_credits_edition`
    FOREIGN KEY (`edition_id`) REFERENCES `book_editions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_book_credits_author`
    FOREIGN KEY (`author_id`) REFERENCES `authors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_book_credits_xor`
    CHECK (
      (`work_id` IS NOT NULL AND `edition_id` IS NULL)
      OR (`work_id` IS NULL AND `edition_id` IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `book_credit_i18n` (
  `credit_id` INT UNSIGNED NOT NULL,
  `lang` CHAR(2) NOT NULL,
  `credited_as` VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`credit_id`, `lang`),
  CONSTRAINT `fk_book_credit_i18n_credit`
    FOREIGN KEY (`credit_id`) REFERENCES `book_credits` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_book_credit_i18n_lang`
    FOREIGN KEY (`lang`) REFERENCES `languages` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `book_edition_publishers` (
  `edition_id` INT UNSIGNED NOT NULL,
  `publisher_id` INT UNSIGNED NOT NULL,
  `role` ENUM('publisher','copublisher','printer','distributor') NOT NULL DEFAULT 'publisher',
  `sort_order` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`edition_id`, `publisher_id`, `role`),
  CONSTRAINT `fk_book_edition_publishers_edition`
    FOREIGN KEY (`edition_id`) REFERENCES `book_editions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_book_edition_publishers_publisher`
    FOREIGN KEY (`publisher_id`) REFERENCES `publishers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `book_series` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `publisher_id` INT UNSIGNED NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_book_series_publisher`
    FOREIGN KEY (`publisher_id`) REFERENCES `publishers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `book_series_i18n` (
  `series_id` INT UNSIGNED NOT NULL,
  `lang` CHAR(2) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(191) NOT NULL,
  `description` TEXT NULL,
  PRIMARY KEY (`series_id`, `lang`),
  UNIQUE KEY `uq_book_series_i18n_slug` (`lang`, `slug`),
  CONSTRAINT `fk_book_series_i18n_series`
    FOREIGN KEY (`series_id`) REFERENCES `book_series` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_book_series_i18n_lang`
    FOREIGN KEY (`lang`) REFERENCES `languages` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `book_edition_series` (
  `edition_id` INT UNSIGNED NOT NULL,
  `series_id` INT UNSIGNED NOT NULL,
  `number_in_series` VARCHAR(16) NOT NULL DEFAULT '',
  PRIMARY KEY (`edition_id`, `series_id`),
  CONSTRAINT `fk_book_edition_series_edition`
    FOREIGN KEY (`edition_id`) REFERENCES `book_editions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_book_edition_series_series`
    FOREIGN KEY (`series_id`) REFERENCES `book_series` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `book_chapters` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `edition_id` INT UNSIGNED NOT NULL,
  `parent_id` INT UNSIGNED NULL DEFAULT NULL,
  `chapter_type` ENUM('part','chapter','section','preface','appendix','afterword') NOT NULL DEFAULT 'chapter',
  `sort_order` INT NOT NULL DEFAULT 0,
  `page_start` INT NULL DEFAULT NULL,
  `page_end` INT NULL DEFAULT NULL,
  `views` INT UNSIGNED NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `legacy_ch_id` INT NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_book_chapters_legacy` (`legacy_ch_id`),
  KEY `idx_book_chapters_edition` (`edition_id`, `sort_order`, `id`),
  CONSTRAINT `fk_book_chapters_edition`
    FOREIGN KEY (`edition_id`) REFERENCES `book_editions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_book_chapters_parent`
    FOREIGN KEY (`parent_id`) REFERENCES `book_chapters` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `book_chapter_i18n` (
  `chapter_id` INT UNSIGNED NOT NULL,
  `lang` CHAR(2) NOT NULL,
  `title` VARCHAR(512) NOT NULL,
  `slug` VARCHAR(191) NOT NULL,
  `meta_description` VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`chapter_id`, `lang`),
  KEY `idx_book_chapter_i18n_slug` (`chapter_id`, `lang`, `slug`),
  CONSTRAINT `fk_book_chapter_i18n_chapter`
    FOREIGN KEY (`chapter_id`) REFERENCES `book_chapters` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_book_chapter_i18n_lang`
    FOREIGN KEY (`lang`) REFERENCES `languages` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `book_chapter_bodies` (
  `chapter_id` INT UNSIGNED NOT NULL,
  `format` ENUM('html','markdown') NOT NULL DEFAULT 'html',
  `body` MEDIUMTEXT NOT NULL,
  `source_hash` CHAR(40) NOT NULL DEFAULT '',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`chapter_id`),
  CONSTRAINT `fk_book_chapter_bodies_chapter`
    FOREIGN KEY (`chapter_id`) REFERENCES `book_chapters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `book_work_rubrics` (
  `work_id` INT UNSIGNED NOT NULL,
  `rubric_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`work_id`, `rubric_id`),
  CONSTRAINT `fk_book_work_rubrics_work`
    FOREIGN KEY (`work_id`) REFERENCES `book_works` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_book_work_rubrics_rubric`
    FOREIGN KEY (`rubric_id`) REFERENCES `book_rubrics` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `book_edition_files` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `edition_id` INT UNSIGNED NOT NULL,
  `file_name` VARCHAR(128) NOT NULL,
  `file_type` INT NOT NULL DEFAULT 0,
  `file_size` INT NOT NULL DEFAULT 0,
  `downloads` INT NOT NULL DEFAULT 0,
  `author` VARCHAR(128) NOT NULL DEFAULT '',
  `upload_date` INT NOT NULL DEFAULT 0,
  `legacy_file_id` INT NULL DEFAULT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_book_edition_files_edition` (`edition_id`, `sort_order`, `id`),
  UNIQUE KEY `uq_book_edition_files_legacy` (`legacy_file_id`),
  CONSTRAINT `fk_book_edition_files_edition`
    FOREIGN KEY (`edition_id`) REFERENCES `book_editions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `book_edition_file_i18n` (
  `file_id` INT UNSIGNED NOT NULL,
  `lang` CHAR(2) NOT NULL,
  `title` VARCHAR(255) NOT NULL DEFAULT '',
  `comment` VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`file_id`, `lang`),
  CONSTRAINT `fk_book_edition_file_i18n_file`
    FOREIGN KEY (`file_id`) REFERENCES `book_edition_files` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_book_edition_file_i18n_lang`
    FOREIGN KEY (`lang`) REFERENCES `languages` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `book_edition_images` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `edition_id` INT UNSIGNED NOT NULL,
  `image_type` ENUM('cover_front','cover_back','spine','scan') NOT NULL DEFAULT 'cover_front',
  `path` VARCHAR(255) NOT NULL,
  `width` INT NULL DEFAULT NULL,
  `height` INT NULL DEFAULT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `legacy_picture_id` INT NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_book_edition_images_edition` (`edition_id`, `image_type`, `sort_order`),
  UNIQUE KEY `uq_book_edition_images_legacy` (`legacy_picture_id`),
  CONSTRAINT `fk_book_edition_images_edition`
    FOREIGN KEY (`edition_id`) REFERENCES `book_editions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `book_edition_image_i18n` (
  `image_id` INT UNSIGNED NOT NULL,
  `lang` CHAR(2) NOT NULL,
  `caption` VARCHAR(255) NOT NULL DEFAULT '',
  `alt` VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`image_id`, `lang`),
  CONSTRAINT `fk_book_edition_image_i18n_image`
    FOREIGN KEY (`image_id`) REFERENCES `book_edition_images` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_book_edition_image_i18n_lang`
    FOREIGN KEY (`lang`) REFERENCES `languages` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
