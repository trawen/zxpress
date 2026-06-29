CREATE TABLE images (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_type TINYINT UNSIGNED NOT NULL,  -- 1=letter, 2=publication, 3=publication_article, 5=periodical_article
    entity_id   INT UNSIGNED NOT NULL,
    format      TINYINT UNSIGNED NOT NULL DEFAULT 1, -- 1=jpg, 2=png, 3=webp, 4=gif
    width       SMALLINT UNSIGNED DEFAULT NULL,
    height      SMALLINT UNSIGNED DEFAULT NULL,
    sort_order  SMALLINT UNSIGNED DEFAULT 0,  -- порядок страниц
    is_active   TINYINT(1) DEFAULT 1,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_entity (entity_type, entity_id)
);
