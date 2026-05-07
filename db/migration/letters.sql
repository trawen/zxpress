CREATE TABLE letters (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    author_from  INT UNSIGNED NOT NULL,
    author_to    INT UNSIGNED NOT NULL,
    title        VARCHAR(255) NOT NULL,
    summary      TEXT DEFAULT NULL,
    body         TEXT DEFAULT NULL,
    date         DATE DEFAULT NULL,
    view_count   INT UNSIGNED DEFAULT 0,
    is_active    TINYINT(1) DEFAULT 1,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (author_from) REFERENCES authors(id) ON DELETE RESTRICT,
    FOREIGN KEY (author_to)   REFERENCES authors(id) ON DELETE RESTRICT
);
