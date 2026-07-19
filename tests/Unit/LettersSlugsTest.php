<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once ZXPRESS_SITE_ROOT . '/includes/letters_slugs.php';

final class LettersSlugsTest extends TestCase
{
    public function testSlugifyTransliteratesAndUsesStrictAsciiAlphabet(): void
    {
        $slug = letters_slugify('«Speccy Rulez!» — Письмо № 26 / M.M.A.');

        self::assertSame('speccy-rulez-pismo-26-m-m-a', $slug);
        self::assertMatchesRegularExpression('/^[a-z0-9-]+$/', $slug);
    }

    public function testSlugifyReplacesDotsAndUnderscoresWithHyphens(): void
    {
        self::assertSame('foo-bar-baz', letters_slugify('Foo_bar.baz'));
    }

    public function testSlugifyCollapsesAndTrimsHyphens(): void
    {
        self::assertSame('hello-world', letters_slugify('---Hello   world---'));
    }

    public function testSlugifyRespectsMaximumLength(): void
    {
        $slug = letters_slugify(str_repeat('long-word ', 40));

        self::assertLessThanOrEqual(LETTERS_SLUG_MAX_LEN, strlen($slug));
        self::assertStringEndsNotWith('-', $slug);
    }

    public function testUrlLetterUsesLanguageSlug(): void
    {
        $row = [
            'id' => 1,
            'slug_ru' => 'gift-dlya-mmcma',
            'slug_en' => 'gift-for-mmcm',
        ];

        self::assertSame('/ru/snailmail/gift-dlya-mmcma', letters_url_letter($row, false));
        self::assertSame('/en/snailmail/gift-for-mmcm', letters_url_letter($row, true));
        self::assertSame('/ru/snailmail', letters_url_catalog(false));
        self::assertSame('/en/snailmail', letters_url_catalog(true));
    }
}
