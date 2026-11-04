-- Ezines v2: parallel model (legacy press/issue/articles stay intact).
-- Requires languages.code (20261007183000_languages_code.sql).
SET NAMES utf8mb4;

-- Content languages used by diskmags (not site UI locales).
INSERT INTO `languages` (`name`, `code`, `is_site_locale`, `sort_order`)
SELECT 'Czech', 'cs', 0, 50 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `languages` WHERE `code` = 'cs' LIMIT 1);

INSERT INTO `languages` (`name`, `code`, `is_site_locale`, `sort_order`)
SELECT 'Slovak', 'sk', 0, 60 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `languages` WHERE `code` = 'sk' LIMIT 1);

CREATE TABLE IF NOT EXISTS `ezines` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `legacy_press_id` INT UNSIGNED NULL DEFAULT NULL,
  `original_language` CHAR(2) NOT NULL DEFAULT 'ru',
  `type` INT NOT NULL DEFAULT 0,
  `city_id` INT NULL DEFAULT NULL,
  `years_from` INT NOT NULL DEFAULT 0,
  `years_to` INT NOT NULL DEFAULT 0,
  `numbers` INT NOT NULL DEFAULT 0,
  `online_issues` INT NOT NULL DEFAULT 0,
  `online_articles` INT NOT NULL DEFAULT 0,
  `ex` VARCHAR(32) NOT NULL DEFAULT '',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ezines_legacy_press` (`legacy_press_id`),
  KEY `idx_ezines_active` (`is_active`),
  CONSTRAINT `fk_ezines_original_language`
    FOREIGN KEY (`original_language`) REFERENCES `languages` (`code`),
  CONSTRAINT `fk_ezines_city`
    FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ezine_i18n` (
  `ezine_id` INT UNSIGNED NOT NULL,
  `lang` CHAR(2) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(191) NULL DEFAULT NULL,
  `description` TEXT NULL,
  `meta_description` VARCHAR(256) NOT NULL DEFAULT '',
  PRIMARY KEY (`ezine_id`, `lang`),
  UNIQUE KEY `uq_ezine_i18n_slug` (`lang`, `slug`),
  CONSTRAINT `fk_ezine_i18n_ezine`
    FOREIGN KEY (`ezine_id`) REFERENCES `ezines` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ezine_i18n_lang`
    FOREIGN KEY (`lang`) REFERENCES `languages` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ezine_issues` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ezine_id` INT UNSIGNED NOT NULL,
  `legacy_issue_id` INT UNSIGNED NULL DEFAULT NULL,
  `issue_date` INT NOT NULL DEFAULT 0,
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `views` INT UNSIGNED NOT NULL DEFAULT 0,
  `is_missing` TINYINT(1) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ezine_issues_legacy` (`legacy_issue_id`),
  KEY `idx_ezine_issues_ezine` (`ezine_id`, `sort_order`, `id`),
  CONSTRAINT `fk_ezine_issues_ezine`
    FOREIGN KEY (`ezine_id`) REFERENCES `ezines` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ezine_issue_i18n` (
  `issue_id` INT UNSIGNED NOT NULL,
  `lang` CHAR(2) NOT NULL,
  `ezine_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(64) NOT NULL DEFAULT '',
  `slug` VARCHAR(255) NULL DEFAULT NULL,
  `description` TEXT NULL,
  `meta_description` VARCHAR(256) NOT NULL DEFAULT '',
  PRIMARY KEY (`issue_id`, `lang`),
  UNIQUE KEY `uq_ezine_issue_i18n_slug` (`ezine_id`, `lang`, `slug`),
  CONSTRAINT `fk_ezine_issue_i18n_issue`
    FOREIGN KEY (`issue_id`) REFERENCES `ezine_issues` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ezine_issue_i18n_ezine`
    FOREIGN KEY (`ezine_id`) REFERENCES `ezines` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ezine_issue_i18n_lang`
    FOREIGN KEY (`lang`) REFERENCES `languages` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ezine_articles` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `issue_id` INT UNSIGNED NOT NULL,
  `legacy_article_id` INT UNSIGNED NULL DEFAULT NULL,
  `original_language` CHAR(2) NOT NULL DEFAULT 'ru',
  `sort_order` INT NOT NULL DEFAULT 1,
  `views` INT UNSIGNED NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `legacy_file` VARCHAR(64) NOT NULL DEFAULT '',
  `legacy_name` VARCHAR(32) NOT NULL DEFAULT '',
  `published_at` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ezine_articles_legacy` (`legacy_article_id`),
  KEY `idx_ezine_articles_issue` (`issue_id`, `sort_order`, `id`),
  CONSTRAINT `fk_ezine_articles_issue`
    FOREIGN KEY (`issue_id`) REFERENCES `ezine_issues` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ezine_articles_original_language`
    FOREIGN KEY (`original_language`) REFERENCES `languages` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ezine_article_i18n` (
  `article_id` INT UNSIGNED NOT NULL,
  `lang` CHAR(2) NOT NULL,
  `issue_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(2048) NOT NULL DEFAULT '',
  `slug` VARCHAR(255) NULL DEFAULT NULL,
  `meta_description` VARCHAR(512) NOT NULL DEFAULT '',
  PRIMARY KEY (`article_id`, `lang`),
  UNIQUE KEY `uq_ezine_article_i18n_slug` (`issue_id`, `lang`, `slug`),
  CONSTRAINT `fk_ezine_article_i18n_article`
    FOREIGN KEY (`article_id`) REFERENCES `ezine_articles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ezine_article_i18n_issue`
    FOREIGN KEY (`issue_id`) REFERENCES `ezine_issues` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ezine_article_i18n_lang`
    FOREIGN KEY (`lang`) REFERENCES `languages` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ezine_article_bodies` (
  `article_id` INT UNSIGNED NOT NULL,
  `lang` CHAR(2) NOT NULL,
  `format` ENUM('html_pre','text_pre','markdown','html','legacy') NOT NULL DEFAULT 'html_pre',
  `body` MEDIUMTEXT NOT NULL,
  `is_primary` TINYINT(1) NOT NULL DEFAULT 1,
  `source_hash` CHAR(40) NOT NULL DEFAULT '',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`article_id`, `lang`, `format`),
  CONSTRAINT `fk_ezine_article_bodies_article`
    FOREIGN KEY (`article_id`) REFERENCES `ezine_articles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ezine_article_bodies_lang`
    FOREIGN KEY (`lang`) REFERENCES `languages` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One primary body per (article, lang).
SET @has_gen := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'ezine_article_bodies'
    AND COLUMN_NAME = 'primary_lang_key'
);
SET @sql := IF(
  @has_gen = 0,
  'ALTER TABLE `ezine_article_bodies`
     ADD COLUMN `primary_lang_key` CHAR(2)
       GENERATED ALWAYS AS (IF(`is_primary` = 1, `lang`, NULL)) STORED AFTER `is_primary`,
     ADD UNIQUE KEY `uq_ezine_article_bodies_primary` (`article_id`, `primary_lang_key`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS `ezine_article_authors` (
  `article_id` INT UNSIGNED NOT NULL,
  `author_id` INT NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`article_id`, `author_id`),
  KEY `idx_ezine_article_authors_author` (`author_id`),
  CONSTRAINT `fk_ezine_article_authors_article`
    FOREIGN KEY (`article_id`) REFERENCES `ezine_articles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ezine_article_authors_author`
    FOREIGN KEY (`author_id`) REFERENCES `authors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
