CREATE TABLE publishers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,

    name_ru VARCHAR(128) NOT NULL,
    name_en VARCHAR(128) NOT NULL DEFAULT '',

    alias_ru VARCHAR(128) NOT NULL DEFAULT '',
    alias_en VARCHAR(128) NOT NULL DEFAULT '',

    form_ru VARCHAR(128) NOT NULL DEFAULT '',
    form_en VARCHAR(128) NOT NULL DEFAULT '',

    description_ru TEXT,
    description_en TEXT,

    city_id INT NOT NULL DEFAULT 1,

    active TINYINT(1) NOT NULL DEFAULT 1,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_publishers_name_ru (name_ru),
    KEY idx_publishers_city_id (city_id)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;
