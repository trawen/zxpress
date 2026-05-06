-- Isolated database for PHPUnit integration tests (runs after zxpress_db.mysql.sql).
-- Application user gets DML on zxpress_test only.

CREATE DATABASE IF NOT EXISTS zxpress_test
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, ALTER ON zxpress_test.* TO 'zxpress_u'@'%';

FLUSH PRIVILEGES;

USE zxpress_test;

CREATE TABLE IF NOT EXISTS integration_db_helpers (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  label VARCHAR(128) NOT NULL,
  value_int INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO integration_db_helpers (id, label, value_int) VALUES
(1, 'fixture_a', 1),
(2, 'fixture_b', 2),
(3, 'fixture_c', 3)
ON DUPLICATE KEY UPDATE label = VALUES(label), value_int = VALUES(value_int);

-- Minimal `comments` mirror for guestbook/article comment integration tests (id_article=0 = guestbook).
CREATE TABLE IF NOT EXISTS comments (
  id INT NOT NULL AUTO_INCREMENT,
  id_article INT NOT NULL,
  date INT NOT NULL,
  nickname VARCHAR(32) NOT NULL,
  email VARCHAR(32) NOT NULL,
  text VARCHAR(2048) NOT NULL,
  ip VARCHAR(45) NOT NULL DEFAULT '',
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
