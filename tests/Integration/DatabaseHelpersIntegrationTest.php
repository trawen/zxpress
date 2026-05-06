<?php

declare(strict_types=1);

/**
 * Integration tests for db_select, db_exec, db_select_in_ints (requires zxpress_test).
 */
final class DatabaseHelpersIntegrationTest extends IntegrationTestCase
{
	private static ?mysqli $db = null;

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
	}

	public static function tearDownAfterClass(): void
	{
		if (self::$db instanceof mysqli) {
			self::$db->close();
			self::$db = null;
		}
	}

	public function testDbSelectWithPlaceholder(): void
	{
		$db = self::$db;
		self::assertInstanceOf(mysqli::class, $db);
		$res = db_select($db, 'SELECT label FROM integration_db_helpers WHERE id = ?', 'i', 1);
		self::assertInstanceOf(mysqli_result::class, $res);
		$row = $res->fetch_assoc();
		self::assertIsArray($row);
		self::assertSame('fixture_a', $row['label'] ?? null);
		$res->free();
	}

	public function testDbSelectWithoutPlaceholder(): void
	{
		$db = self::$db;
		$res = db_select($db, 'SELECT COUNT(*) AS c FROM integration_db_helpers');
		self::assertInstanceOf(mysqli_result::class, $res);
		$row = $res->fetch_assoc();
		self::assertGreaterThanOrEqual(3, (int)($row['c'] ?? 0));
		$res->free();
	}

	public function testDbExecInsertAndDelete(): void
	{
		$db = self::$db;
		$label = 'phpunit_db_exec_' . bin2hex(random_bytes(4));
		$ok = db_exec(
			$db,
			'INSERT INTO integration_db_helpers (label, value_int) VALUES (?, ?)',
			'si',
			$label,
			99
		);
		self::assertTrue($ok, 'insert failed: ' . ($db->error ?: 'unknown'));

		$res = db_select($db, 'SELECT id FROM integration_db_helpers WHERE label = ? LIMIT 1', 's', $label);
		self::assertInstanceOf(mysqli_result::class, $res);
		$row = $res->fetch_assoc();
		$res->free();
		self::assertIsArray($row);
		$id = (int)($row['id'] ?? 0);
		self::assertGreaterThan(0, $id);

		$del = db_exec($db, 'DELETE FROM integration_db_helpers WHERE id = ?', 'i', $id);
		self::assertTrue($del);
	}

	public function testDbSelectInInts(): void
	{
		$db = self::$db;
		$sql = 'SELECT id, label FROM integration_db_helpers WHERE id IN (__IN__) ORDER BY id';
		$res = db_select_in_ints($db, $sql, [3, 1, 2]);
		self::assertInstanceOf(mysqli_result::class, $res);
		$ids = [];
		while ($row = $res->fetch_assoc()) {
			$ids[] = (int)$row['id'];
		}
		$res->free();
		self::assertSame([1, 2, 3], $ids);
	}

	public function testDbSelectInIntsEmptyReturnsFalse(): void
	{
		$db = self::$db;
		$res = db_select_in_ints($db, 'SELECT id FROM integration_db_helpers WHERE id IN (__IN__)', []);
		self::assertFalse($res);
	}
}
