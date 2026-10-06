-- Remove duplicate cities (same name + name_eng + country_id).
-- Keep a referenced id when one exists; otherwise keep the lowest id.
-- Reassign FKs from discarded ids to the kept id, then delete discarded rows.
SET NAMES utf8mb4;

DROP TEMPORARY TABLE IF EXISTS city_dup_drop;
CREATE TEMPORARY TABLE city_dup_drop (
  keep_id INT NOT NULL,
  drop_id INT NOT NULL,
  PRIMARY KEY (drop_id)
);

INSERT INTO city_dup_drop (keep_id, drop_id)
SELECT keep_id, drop_id
FROM (
  SELECT
    c.id AS drop_id,
    COALESCE(
      (
        SELECT c2.id
        FROM cities c2
        WHERE c2.name = c.name
          AND c2.name_eng = c.name_eng
          AND c2.country_id = c.country_id
          AND (
            EXISTS (SELECT 1 FROM authors a WHERE a.city_id = c2.id)
            OR EXISTS (SELECT 1 FROM press p WHERE p.city = c2.id)
            OR EXISTS (SELECT 1 FROM books b WHERE b.city_id = c2.id)
            OR EXISTS (SELECT 1 FROM publications pu WHERE pu.city_id = c2.id)
            OR EXISTS (SELECT 1 FROM periodicals pe WHERE pe.city_id = c2.id)
            OR EXISTS (SELECT 1 FROM publishers pb WHERE pb.city_id = c2.id)
          )
        ORDER BY c2.id
        LIMIT 1
      ),
      (
        SELECT MIN(c3.id)
        FROM cities c3
        WHERE c3.name = c.name
          AND c3.name_eng = c.name_eng
          AND c3.country_id = c.country_id
      )
    ) AS keep_id
  FROM cities c
  INNER JOIN (
    SELECT name, name_eng, country_id
    FROM cities
    GROUP BY name, name_eng, country_id
    HAVING COUNT(*) > 1
  ) d
    ON d.name = c.name
   AND d.name_eng = c.name_eng
   AND d.country_id = c.country_id
) x
WHERE drop_id <> keep_id;

UPDATE authors a
INNER JOIN city_dup_drop d ON a.city_id = d.drop_id
SET a.city_id = d.keep_id;

UPDATE press p
INNER JOIN city_dup_drop d ON p.city = d.drop_id
SET p.city = d.keep_id;

UPDATE books b
INNER JOIN city_dup_drop d ON b.city_id = d.drop_id
SET b.city_id = d.keep_id;

UPDATE publications pu
INNER JOIN city_dup_drop d ON pu.city_id = d.drop_id
SET pu.city_id = d.keep_id;

UPDATE periodicals pe
INNER JOIN city_dup_drop d ON pe.city_id = d.drop_id
SET pe.city_id = d.keep_id;

UPDATE publishers pb
INNER JOIN city_dup_drop d ON pb.city_id = d.drop_id
SET pb.city_id = d.keep_id;

DELETE c
FROM cities c
INNER JOIN city_dup_drop d ON c.id = d.drop_id;

DROP TEMPORARY TABLE IF EXISTS city_dup_drop;
