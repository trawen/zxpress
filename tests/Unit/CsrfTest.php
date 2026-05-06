<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for includes/csrf.php using an in-memory $_SESSION mock.
 *
 * csrf_verify() on mismatch calls die() — that path is not asserted here (use HTTP/integration tests).
 */
final class CsrfTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		csrf_test_reset_session();
	}

	protected function tearDown(): void
	{
		csrf_test_reset_session();
		parent::tearDown();
	}

	public function testCsrfTokenIsHex64OnFirstCall(): void
	{
		$t = csrf_token();
		self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $t);
		self::assertSame(64, strlen($t), 'token length (no full value logged in CI)');
	}

	public function testCsrfTokenStableWithinSession(): void
	{
		$a = csrf_token();
		$b = csrf_token();
		self::assertSame($a, $b);
	}

	public function testCsrfFieldIsHiddenInputWithEscapedValue(): void
	{
		$html = csrf_field();
		self::assertStringContainsString('type="hidden"', $html);
		self::assertStringContainsString('name="csrf_token"', $html);
		self::assertStringContainsString('value="', $html);
		self::assertStringNotContainsString('<script', strtolower($html));
	}

	public function testCsrfVerifyAcceptsMatchingPostToken(): void
	{
		$tok = csrf_token();
		$_POST['csrf_token'] = $tok;
		csrf_verify();
		self::assertTrue(true);
	}
}
