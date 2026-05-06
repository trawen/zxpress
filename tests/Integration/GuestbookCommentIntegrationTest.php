<?php

declare(strict_types=1);

/**
 * Guestbook uses id_article=0 in `comments` (see guestbook.php + comments.php).
 */
final class GuestbookCommentIntegrationTest extends IntegrationTestCase
{
	private static ?mysqli $db = null;
	private static int $insertId = 0;

	public static function setUpBeforeClass(): void
	{
		self::requireIntegrationEnv();

		$host = getenv('DB_HOST') ?: '127.0.0.1';
		$port = (int)(getenv('DB_PORT') ?: 3306);
		$user = getenv('DB_USER') ?: 'zxpress_u';
		$pass = getenv('DB_PASS') ?: '';
		$dbname = getenv('ZXPRESS_TEST_DB_NAME') ?: 'zxpress_test';

		self::logDbTarget($host, $port, $dbname);

		if ($pass === '' && getenv('ALLOW_EMPTY_DB_PASSWORD') !== '1') {
			self::markTestSkipped('DB_PASS empty; set ALLOW_EMPTY_DB_PASSWORD=1 for local dev or provide DB_PASS.');
		}

		self::$db = @mysqli_connect($host, $user, $pass, $dbname, $port);
		if (!self::$db) {
			self::markTestSkipped('mysqli_connect failed: ' . mysqli_connect_error());
		}
		self::$db->set_charset('utf8mb4');

		$res = self::$db->query("SHOW TABLES LIKE 'comments'");
		if (!$res || $res->num_rows === 0) {
			self::markTestSkipped('Table comments missing in zxpress_test; apply db/init/zzz-zxpress_test.sql');
		}
		$res->free();
	}

	public static function tearDownAfterClass(): void
	{
		if (self::$db instanceof mysqli && self::$insertId > 0) {
			db_exec(self::$db, 'DELETE FROM comments WHERE id = ?', 'i', self::$insertId);
		}
		if (self::$db instanceof mysqli) {
			self::$db->close();
			self::$db = null;
		}
	}

	public function testInsertGuestbookCommentRow(): void
	{
		$db = self::$db;
		self::assertInstanceOf(mysqli::class, $db);

		$label = 'e2e_guestbook_' . bin2hex(random_bytes(3));
		$now = time();
		$ok = db_exec(
			$db,
			'INSERT INTO comments (id_article, date, nickname, email, text, ip) VALUES (?, ?, ?, ?, ?, ?)',
			'iissss',
			0,
			$now,
			$label,
			'test@e2e',
			'integration test body',
			'127.0.0.1'
		);
		self::assertTrue($ok, $db->error ?: 'insert failed');
		self::$insertId = (int)$db->insert_id;
		self::assertGreaterThan(0, self::$insertId);

		$res = db_select($db, 'SELECT nickname, text FROM comments WHERE id = ?', 'i', self::$insertId);
		self::assertInstanceOf(mysqli_result::class, $res);
		$row = $res->fetch_assoc();
		$res->free();
		self::assertSame($label, $row['nickname'] ?? null);
		self::assertStringContainsString('integration test', (string)($row['text'] ?? ''));
	}
}
