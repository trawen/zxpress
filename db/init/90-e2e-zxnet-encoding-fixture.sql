-- Playwright / manual regression: ZXNet topic with HTML entities in `echos_zxnet.text`.
-- After plain_text_decode_entities + Smarty escape, the page must not show visible &quot;
-- URL: /zxnet/e2e.talk/911001
-- IDs 911001 are reserved for this fixture (ON DUPLICATE KEY UPDATE keeps it idempotent).

USE zxpress_db;

INSERT INTO echos_titles2 (id, title, nm, date_from, date_to, description)
VALUES (911001, 'e2e.talk', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 'E2E encoding fixture')
ON DUPLICATE KEY UPDATE
	title = VALUES(title),
	nm = VALUES(nm),
	date_from = VALUES(date_from),
	date_to = VALUES(date_to),
	description = VALUES(description);

INSERT INTO echos_subjs2 (id, echo_id, title, nm, date_from, date_to)
VALUES (911001, 911001, 'Encoding fixture topic', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP())
ON DUPLICATE KEY UPDATE
	echo_id = VALUES(echo_id),
	title = VALUES(title),
	nm = VALUES(nm),
	date_from = VALUES(date_from),
	date_to = VALUES(date_to);

INSERT INTO echos_zxnet (id, echo_id, subj_id, `date`, name_from, name_to, text, nm, tag, tear, origin)
VALUES (
	911001,
	911001,
	911001,
	UNIX_TIMESTAMP(),
	'E2E',
	'All',
	'Quote test: &quot;ZX Spectrum&quot; must show as straight quotes in the page.',
	0,
	'',
	'',
	''
)
ON DUPLICATE KEY UPDATE
	echo_id = VALUES(echo_id),
	subj_id = VALUES(subj_id),
	`date` = VALUES(`date`),
	name_from = VALUES(name_from),
	name_to = VALUES(name_to),
	text = VALUES(text),
	nm = VALUES(nm),
	tag = VALUES(tag),
	tear = VALUES(tear),
	origin = VALUES(origin);
