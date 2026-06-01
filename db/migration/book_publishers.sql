CREATE TABLE book_publishers (
    book_id INT UNSIGNED NOT NULL,
    publisher_id INT UNSIGNED NOT NULL,

    PRIMARY KEY (book_id, publisher_id),

    KEY idx_book_id (book_id),
    KEY idx_publisher_id (publisher_id),

    CONSTRAINT fk_bp_book
        FOREIGN KEY (book_id)
        REFERENCES books(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_bp_publisher
        FOREIGN KEY (publisher_id)
        REFERENCES publishers(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;
