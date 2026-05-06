<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ContentNormalizeTest extends TestCase
{
	public function testPlainTextNormalizeStripsTagsAndEntities(): void
	{
		self::assertSame('Foo & Bar', plain_text_normalize_for_storage(' <b>Foo</b> &amp; Bar '));
	}

	public function testPlainTextNormalizeStripsBbcode(): void
	{
		self::assertSame('Hello - World', plain_text_normalize_for_storage('[b]Hello[/b] - World'));
	}

	public function testHtmlLegacyNormalizeRgbModeEscapesStrayLt(): void
	{
		require_once dirname(__DIR__, 2) . '/site/html_fix.php';
		$out = html_legacy_normalize('x < y', HTML_LEGACY_FIX_MODE_RGB, 0);
		self::assertStringContainsString('&#60;', $out);
	}

	public function testHtmlLegacyNormalizeRichModeAllowsImgPrefix(): void
	{
		require_once dirname(__DIR__, 2) . '/site/html_fix.php';
		$out = html_legacy_normalize('<img src="a">', HTML_LEGACY_FIX_MODE_RICH, 1);
		self::assertStringContainsString('<img', $out);
	}
}
