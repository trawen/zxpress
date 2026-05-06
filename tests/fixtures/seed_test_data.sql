-- Mirror of seed rows in db/init/zzz-zxpress_test.sql (integration_db_helpers).
-- Apply manually if you recreate zxpress_test without full docker init.

USE zxpress_test;

INSERT INTO integration_db_helpers (id, label, value_int) VALUES
(1, 'fixture_a', 1),
(2, 'fixture_b', 2),
(3, 'fixture_c', 3)
ON DUPLICATE KEY UPDATE label = VALUES(label), value_int = VALUES(value_int);
