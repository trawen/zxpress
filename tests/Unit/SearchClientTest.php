<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for pure functions in search_client.php (manticore_escape).
 * Duplicates escape cases from tests/test_search_client.php for PHPUnit.
 */
final class SearchClientTest extends TestCase
{
	public function testEscapeSingleQuote(): void
	{
		self::assertSame("test\\'", manticore_escape("test'"));
	}

	public function testEscapeBackslashBeforeQuoteInjectionVector(): void
	{
		self::assertSame("test\\\\\\'", manticore_escape("test\\'"));
	}

	public function testEscapePlainStringUnchanged(): void
	{
		self::assertSame('normal text', manticore_escape('normal text'));
	}

	public function testEscapeBackslashOnly(): void
	{
		self::assertSame('back\\\\slash', manticore_escape('back\\slash'));
	}

	public function testEscapeMultipleQuotes(): void
	{
		self::assertSame("it\\'s a \\'test\\'", manticore_escape("it's a 'test'"));
	}

	public function testEscapeEmptyString(): void
	{
		self::assertSame('', manticore_escape(''));
	}
}
