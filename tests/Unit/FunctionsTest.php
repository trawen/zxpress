<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for pure helpers in includes/functions.php (no mysqli / DB).
 */
final class FunctionsTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		date_default_timezone_set('UTC');
	}

	public function testTitleCollapsesMultipleSpaces(): void
	{
		self::assertSame('a b c', title('a  b   c'));
	}

	public function testTitleBoldBeforeDash(): void
	{
		$out = title('Foo - Bar');
		self::assertStringContainsString('<b>Foo</b>', $out);
		self::assertStringContainsString(' - Bar', $out);
	}

	public function testTitleEscapesSingleQuote(): void
	{
		self::assertStringContainsString('&#039;', title("O'Reilly - Book"));
	}

	public function testNl2pWrapsNonEmptyLines(): void
	{
		$html = nl2p("Line1\n\nLine2");
		self::assertStringContainsString("<p class='news'>", $html);
		self::assertStringContainsString('Line1', $html);
		self::assertStringContainsString('Line2', $html);
	}

	public function testNl2pEscapesHtmlInLine(): void
	{
		$html = nl2p('<script>x</script>');
		self::assertStringNotContainsString('<script>', $html);
		self::assertStringContainsString('&lt;script&gt;', $html);
	}

	public function testTitlePlainStripsTags(): void
	{
		$plain = title_plain('Foo - Bar');
		self::assertSame('Foo - Bar', $plain);
		self::assertStringNotContainsString('<b>', $plain);
	}

	public function testTitlePlainStripsBbcode(): void
	{
		$plain = title_plain('[b]Hello[/b] - World');
		self::assertStringNotContainsString('[b]', $plain);
		self::assertStringNotContainsString('[/b]', $plain);
		self::assertStringContainsString('Hello', $plain);
	}

	public function testTitlePlainDecodesEntitiesNoVisibleNumericApostrophe(): void
	{
		$plain = title_plain("7 Origins - origin&#039;s test");
		self::assertStringContainsString("'", $plain);
		self::assertStringNotContainsString('&#039;', $plain);
	}

	public function testPlainTextDecodeEntitiesDecodesQuot(): void
	{
		self::assertSame('"ZX"', plain_text_decode_entities('&quot;ZX&quot;'));
	}

	public function testPlainTextDecodeEntitiesNullAndEmpty(): void
	{
		self::assertSame('', plain_text_decode_entities(null));
		self::assertSame('', plain_text_decode_entities(''));
	}

	public function testArticleTitleListHtmlRendersBbcodeBold(): void
	{
		$html = article_title_list_html('[b]Part A[/b] - Part B');
		self::assertStringContainsString('<b>', $html);
		self::assertStringNotContainsString('[b]', $html);
		self::assertStringContainsString('Part A', $html);
	}

	public function testRusdateMonthPlaceholder(): void
	{
		$ts = strtotime('2020-01-15 12:00:00 UTC');
		$out = rusdate($ts, 'j %MONTH% Y', 0);
		self::assertSame('15 января 2020', $out);
	}

	public function testGetNumEndingOne(): void
	{
		$e = ['яблоко', 'яблока', 'яблок'];
		self::assertSame('яблоко', getNumEnding(1, $e));
		self::assertSame('яблока', getNumEnding(3, $e));
		self::assertSame('яблок', getNumEnding(5, $e));
	}

	public function testGetNumEndingTeensUseThirdForm(): void
	{
		$e = ['яблоко', 'яблока', 'яблок'];
		self::assertSame('яблок', getNumEnding(11, $e));
		self::assertSame('яблок', getNumEnding(19, $e));
	}

	public function testWordLimiterShortTextUnchanged(): void
	{
		self::assertSame('short', word_limiter('short', 30));
	}

	public function testFriendlyUrlFromString(): void
	{
		$u = friendly_url('Привет мир');
		self::assertStringContainsString('privet', $u);
		self::assertStringNotContainsString(' ', $u);
	}

	public function testFriendlyUrlFromArray(): void
	{
		$u = friendly_url(['string' => 'Тест']);
		self::assertSame($u, friendly_url('Тест'));
	}

	public function testNiceurlBasic(): void
	{
		self::assertSame('privet', niceurl('Привет'));
	}

	public function testNiceurlStripsBoldTags(): void
	{
		// Bold segments are removed entirely before slugify; remainder is " bar" → "bar".
		self::assertSame('bar', niceurl('<b>Foo</b> bar'));
	}

	public function testNiceurlMaxLength(): void
	{
		$long = str_repeat('a', 100);
		self::assertLessThanOrEqual(60, strlen(niceurl($long)));
	}
}
