-- Журнал / книга / газета

CREATE TABLE publications (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title_ru     VARCHAR(255) NOT NULL,
    title_en     VARCHAR(255) DEFAULT NULL,
    summary_ru   TEXT DEFAULT NULL,
    summary_en   TEXT DEFAULT NULL,
    type         TINYINT UNSIGNED NOT NULL,  -- 1=журнал, 2=книга, 3=газета
    country_id   INT DEFAULT NULL,
    city_id      INT DEFAULT NULL,
    published_at DATE DEFAULT NULL,
    is_active    TINYINT(1) DEFAULT 1,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (country_id) REFERENCES countries(id) ON DELETE SET NULL,
    FOREIGN KEY (city_id)    REFERENCES cities(id)    ON DELETE SET NULL
);


-- Статья / материал внутри издания
CREATE TABLE publication_articles (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    publication_id INT UNSIGNED NOT NULL,
    title_ru       VARCHAR(255) NOT NULL,
    title_en       VARCHAR(255) DEFAULT NULL,
    summary_ru     TEXT DEFAULT NULL,
    summary_en     TEXT DEFAULT NULL,
    body_ru        TEXT DEFAULT NULL,
    body_en        TEXT DEFAULT NULL,
    page_from      SMALLINT UNSIGNED DEFAULT NULL,
    page_to        SMALLINT UNSIGNED DEFAULT NULL,
    view_count     INT UNSIGNED DEFAULT 0,
    is_active      TINYINT(1) DEFAULT 1,
    created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (publication_id) REFERENCES publications(id) ON DELETE RESTRICT
);
