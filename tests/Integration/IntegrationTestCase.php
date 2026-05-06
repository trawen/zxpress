<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Base for integration tests (Docker DB / HTTP / Manticore).
 * Skips unless ZXPRESS_INTEGRATION_TESTS=1.
 */
abstract class IntegrationTestCase extends TestCase
{
	protected static function requireIntegrationEnv(): void
	{
		if (getenv('ZXPRESS_INTEGRATION_TESTS') !== '1') {
			self::markTestSkipped('Set ZXPRESS_INTEGRATION_TESTS=1 (see docs/testing.md).');
		}
	}

	/**
	 * Log connection target without password.
	 */
	protected static function logDbTarget(string $host, int $port, string $dbname): void
	{
		error_log('[integration] mysqli target host=' . $host . ' port=' . $port . ' db=' . $dbname);
	}
}
