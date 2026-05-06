-- Restrict application user privileges (run after schema creation)
-- The app user gets only DML; no GRANT, FILE, PROCESS, SUPER, or DDL

REVOKE ALL PRIVILEGES, GRANT OPTION FROM 'zxpress_u'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON zxpress_db.* TO 'zxpress_u'@'%';

-- Read-only user for Manticore indexing
CREATE USER IF NOT EXISTS 'manticore_ro'@'%' IDENTIFIED BY 'mnt_r0_zxpr3ss_2026';
GRANT SELECT ON zxpress_db.* TO 'manticore_ro'@'%';

FLUSH PRIVILEGES;
