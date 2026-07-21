CREATE TABLE authors (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    nickname     VARCHAR(100) NOT NULL,
    name_ru         VARCHAR(255) DEFAULT NULL,
    name_en         VARCHAR(255) DEFAULT NULL,
    group_name   VARCHAR(255) DEFAULT NULL,
    slug_ru      VARCHAR(191) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
    slug_en      VARCHAR(191) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
    country_id   INT DEFAULT NULL,
    city_id      INT DEFAULT NULL,
    user_id      INT DEFAULT NULL,
    is_active    TINYINT(1) DEFAULT 1,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_authors_slug_ru (slug_ru),
    UNIQUE KEY uq_authors_slug_en (slug_en),
    FOREIGN KEY (country_id) REFERENCES countries(id) ON DELETE SET NULL,
    FOREIGN KEY (city_id)    REFERENCES cities(id)    ON DELETE SET NULL,
    FOREIGN KEY (user_id)    REFERENCES users(id)     ON DELETE SET NULL
);
