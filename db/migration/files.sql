CREATE TABLE files_ (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_type TINYINT UNSIGNED NOT NULL,  -- 1=letter, 2=publication_article
    entity_id   INT UNSIGNED NOT NULL,
    format      TINYINT UNSIGNED NOT NULL DEFAULT 1, -- 1=pdf, 2=doc, 3=html, 4=txt, 5=
    download_count  INT UNSIGNED DEFAULT 0,  -- порядок страниц
    size  INT UNSIGNED DEFAULT 0,  -- размер файла в байтах
    is_active   TINYINT(1) DEFAULT 1,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_entity (entity_type, entity_id)
);
