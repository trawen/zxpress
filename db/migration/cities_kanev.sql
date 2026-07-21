-- Kanev, Ukraine
SET NAMES utf8mb4;

INSERT INTO countries (country_name, country_name_eng, short)
SELECT 'Украина', 'Ukraine', 'UA'
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM countries
    WHERE country_name = 'Украина' OR country_name_eng = 'Ukraine' OR short = 'UA'
);

INSERT INTO cities (name, name_eng, country_id, lat, lng)
SELECT 'Канев', 'Kanev', c.id, 49.7583, 31.4600
FROM countries c
WHERE (c.country_name = 'Украина' OR c.country_name_eng = 'Ukraine')
  AND NOT EXISTS (
    SELECT 1 FROM cities WHERE name = 'Канев' OR name_eng = 'Kanev'
  )
LIMIT 1;

-- Fix mojibake if an earlier import stored the name with wrong charset.
UPDATE cities SET name = 'Канев' WHERE name_eng = 'Kanev' AND name <> 'Канев';
