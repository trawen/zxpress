-- Universal activity feed (batches + atomic events).
-- Covers ezines, books, periodicals, publications, letters, zxnet, news, metadata.

CREATE TABLE IF NOT EXISTS `activity_batch` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `created_at` INT UNSIGNED NOT NULL,
  `closed_at` INT UNSIGNED NULL DEFAULT NULL,
  `actor_user_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `domain` VARCHAR(32) NOT NULL DEFAULT 'ezine',
  `root_type` VARCHAR(32) NOT NULL DEFAULT '',
  `root_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `title_ru` VARCHAR(255) NOT NULL DEFAULT '',
  `title_en` VARCHAR(255) NOT NULL DEFAULT '',
  `url_ru` VARCHAR(512) NOT NULL DEFAULT '',
  `url_en` VARCHAR(512) NOT NULL DEFAULT '',
  `summary_ru` VARCHAR(512) NOT NULL DEFAULT '',
  `summary_en` VARCHAR(512) NOT NULL DEFAULT '',
  `thumb_url` VARCHAR(512) NULL DEFAULT NULL,
  `items_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `public_items_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `is_public` TINYINT(1) NOT NULL DEFAULT 1,
  `source` VARCHAR(64) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_activity_batch_feed` (`is_public`, `created_at`, `id`),
  KEY `idx_activity_batch_root` (`root_type`, `root_id`, `created_at`),
  KEY `idx_activity_batch_domain` (`domain`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `activity` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `batch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `created_at` INT UNSIGNED NOT NULL,
  `actor_user_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `verb` VARCHAR(32) NOT NULL,
  `object_type` VARCHAR(32) NOT NULL,
  `object_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `parent_type` VARCHAR(32) NULL DEFAULT NULL,
  `parent_id` INT UNSIGNED NULL DEFAULT NULL,
  `action` VARCHAR(64) NOT NULL DEFAULT '',
  `event_scope` VARCHAR(16) NOT NULL DEFAULT 'content',
  `is_public` TINYINT(1) NOT NULL DEFAULT 1,
  `title_ru` VARCHAR(255) NOT NULL DEFAULT '',
  `title_en` VARCHAR(255) NOT NULL DEFAULT '',
  `url_ru` VARCHAR(512) NOT NULL DEFAULT '',
  `url_en` VARCHAR(512) NOT NULL DEFAULT '',
  `thumb_url` VARCHAR(512) NULL DEFAULT NULL,
  `before_json` JSON NULL,
  `after_json` JSON NULL,
  `meta_json` JSON NULL,
  `legacy_log_id` INT UNSIGNED NULL DEFAULT NULL,
  `legacy_log_type` INT NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_activity_feed` (`is_public`, `created_at`, `id`),
  KEY `idx_activity_batch` (`batch_id`, `created_at`),
  KEY `idx_activity_object` (`object_type`, `object_id`),
  KEY `idx_activity_parent` (`parent_type`, `parent_id`, `created_at`),
  KEY `idx_activity_scope` (`event_scope`, `created_at`),
  KEY `idx_activity_action` (`action`, `created_at`),
  KEY `idx_activity_legacy` (`legacy_log_id`),
  CONSTRAINT `fk_activity_batch` FOREIGN KEY (`batch_id`) REFERENCES `activity_batch` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mirror every legacy log insert into activity (covers all old admin paths).
DROP TRIGGER IF EXISTS `trg_log_ai_activity`;
DELIMITER $$
CREATE TRIGGER `trg_log_ai_activity`
AFTER INSERT ON `log`
FOR EACH ROW
BEGIN
  DECLARE v_verb VARCHAR(32) DEFAULT 'updated';
  DECLARE v_object_type VARCHAR(32) DEFAULT 'legacy';
  DECLARE v_object_id INT UNSIGNED DEFAULT 0;
  DECLARE v_parent_type VARCHAR(32) DEFAULT NULL;
  DECLARE v_parent_id INT UNSIGNED DEFAULT NULL;
  DECLARE v_action VARCHAR(64) DEFAULT '';
  DECLARE v_scope VARCHAR(16) DEFAULT 'system';
  DECLARE v_public TINYINT DEFAULT 0;
  DECLARE v_batch BIGINT UNSIGNED DEFAULT NULL;

  SET v_batch = NULLIF(@activity_batch_id, 0);
  SET v_action = CONCAT('legacy.type.', NEW.type);

  IF NEW.type = 1 THEN
    SET v_verb='created', v_object_type='article', v_object_id=NEW.id_article,
        v_parent_type='issue', v_parent_id=NEW.id_issue,
        v_action='article.created', v_scope='content', v_public=1;
  ELSEIF NEW.type = 2 THEN
    SET v_verb='uploaded', v_object_type='screen', v_object_id=NEW.id_screen,
        v_parent_type='issue', v_parent_id=NEW.id_issue,
        v_action='screen.uploaded', v_scope='content', v_public=1;
  ELSEIF NEW.type = 3 THEN
    SET v_verb='uploaded', v_object_type='illustration', v_object_id=NEW.id_screen,
        v_parent_type='issue', v_parent_id=NEW.id_issue,
        v_action='illustration.uploaded', v_scope='content', v_public=1;
  ELSEIF NEW.type = 4 THEN
    SET v_verb='created', v_object_type='chapter', v_object_id=NEW.id_article,
        v_parent_type='book', v_parent_id=NEW.id_press,
        v_action='chapter.created', v_scope='content', v_public=1;
  ELSEIF NEW.type = 100 THEN
    SET v_verb='created', v_object_type='book', v_object_id=NEW.id_press,
        v_action='book.created', v_scope='content', v_public=1;
  ELSEIF NEW.type = 101 THEN
    SET v_verb='uploaded', v_object_type='book_file', v_object_id=IF(NEW.id_screen>0, NEW.id_screen, NEW.id_press),
        v_parent_type='book', v_parent_id=NEW.id_press,
        v_action='book_file.uploaded', v_scope='content', v_public=1;
  ELSEIF NEW.type = 102 THEN
    SET v_verb='uploaded', v_object_type='book_image', v_object_id=IF(NEW.id_screen>0, NEW.id_screen, NEW.id_press),
        v_parent_type='book', v_parent_id=NEW.id_press,
        v_action='book_image.uploaded', v_scope='content', v_public=1;
  ELSEIF NEW.type = 256 THEN
    SET v_verb='deleted', v_object_type='article', v_object_id=NEW.id_article,
        v_parent_type='issue', v_parent_id=NEW.id_issue,
        v_action='article.deleted', v_scope='content', v_public=0;
  ELSEIF NEW.type IN (111,128,160) THEN
    SET v_verb='updated', v_object_type='book', v_object_id=NEW.id_press,
        v_action=CONCAT('book.meta.', NEW.type), v_scope='metadata', v_public=0;
  END IF;

  IF v_object_id = 0 THEN
    SET v_object_id = IF(NEW.id_article>0, NEW.id_article, IF(NEW.id_press>0, NEW.id_press, NEW.id_issue));
  END IF;

  INSERT INTO activity (
    batch_id, created_at, actor_user_id, verb, object_type, object_id,
    parent_type, parent_id, action, event_scope, is_public,
    title_ru, title_en, url_ru, url_en, thumb_url,
    meta_json, legacy_log_id, legacy_log_type
  ) VALUES (
    v_batch, NEW.date, NEW.id_user, v_verb, v_object_type, v_object_id,
    v_parent_type, v_parent_id, v_action, v_scope, v_public,
    '', '', '', '', NULL,
    JSON_OBJECT(
      'legacy_type', NEW.type,
      'id_press', NEW.id_press,
      'id_issue', NEW.id_issue,
      'id_article', NEW.id_article,
      'id_screen', NEW.id_screen,
      'id_cover', NEW.id_cover
    ),
    NEW.id, NEW.type
  );
END$$
DELIMITER ;
