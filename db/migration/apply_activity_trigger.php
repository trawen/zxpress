<?php
/**
 * Apply trg_log_ai_activity (MySQL client DELIMITER is awkward in dumps).
 * Usage: php apply_activity_trigger.php
 */
$dbHost = getenv('DB_HOST') ?: 'db';
$dbUser = getenv('DB_USER') ?: 'zxpress_u';
$dbPass = getenv('DB_PASS') ?: '';
$dbName = getenv('DB_NAME') ?: 'zxpress_db';

$db = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($db->connect_errno) {
	fwrite(STDERR, "connect failed: {$db->connect_error}\n");
	exit(1);
}
$db->set_charset('utf8mb4');

$db->query('DROP TRIGGER IF EXISTS trg_log_ai_activity');
if ($db->errno) {
	fwrite(STDERR, "DROP TRIGGER: {$db->error}\n");
}

$sql = <<<'SQL'
CREATE TRIGGER trg_log_ai_activity
AFTER INSERT ON log
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
END
SQL;

if (!$db->query($sql)) {
	fwrite(STDERR, "CREATE TRIGGER failed: {$db->errno} {$db->error}\n");
	exit(1);
}

$r = $db->query(
	'SELECT TRIGGER_NAME FROM information_schema.TRIGGERS '
	. 'WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME="trg_log_ai_activity"'
);
$row = $r ? $r->fetch_assoc() : null;
echo $row ? ("OK trigger={$row['TRIGGER_NAME']}\n") : "WARN: trigger not listed\n";
