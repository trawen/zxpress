-- Allow large GG HTML bodies and 4-byte UTF-8.
ALTER TABLE echos_zxnet
  MODIFY COLUMN text MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;
