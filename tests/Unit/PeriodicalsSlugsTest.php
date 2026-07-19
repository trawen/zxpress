<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once ZXPRESS_SITE_ROOT . '/includes/periodical_issue_files.php';
require_once ZXPRESS_SITE_ROOT . '/includes/periodicals_slugs.php';

final class PeriodicalsSlugsTest extends TestCase
{
    public function testSlugifyTransliteratesCyrillic(): void
    {
        self::assertSame('zxpress', per_slugify('ZXPress'));
        self::assertSame('zhurnal', per_slugify('Журнал'));
    }

    public function testSlugFromTitle(): void
    {
        self::assertSame('spectr', per_slug_from_title('Спектр'));
        self::assertSame('spectrum', per_slug_from_title('Spectrum'));
    }

    public function testRowSlugPicksLanguageColumn(): void
    {
        $row = ['slug_ru' => 'spectr', 'slug_en' => 'spectrum'];
        self::assertSame('spectr', per_row_slug($row, false));
        self::assertSame('spectrum', per_row_slug($row, true));
        self::assertSame('spectr', per_row_slug(['slug_ru' => 'spectr', 'slug_en' => ''], true));
    }

    public function testDefaultIssueSlugUsesYearAndNumber(): void
    {
        self::assertSame(
            '2024-3',
            per_slug_default_issue_ru([
                'issue_year' => 2024,
                'issue_no' => '3',
                'title_ru' => '',
                'title_en' => '',
            ])
        );
    }

    public function testSlugifyStripsDotsAndCommas(): void
    {
        self::assertSame('foo-bar-baz', per_slugify('foo, bar. baz'));
        self::assertSame('hello-world', per_slugify('hello, world.'));
    }

    public function testDefaultArticleSlugUsesMetaDescription(): void
    {
        self::assertSame(
            'kratkoe-opisanie-stati',
            per_slug_default_article_ru([
                'meta_description_ru' => 'Краткое описание статьи.',
                'title_ru' => 'Длинный заголовок',
            ])
        );
        self::assertSame(
            'short-summary',
            per_slug_default_article_en([
                'meta_description_en' => 'Short summary.',
                'title_en' => 'Long title',
            ])
        );
    }

    public function testDefaultPublisherSlugUsesMetaDescription(): void
    {
        self::assertSame(
            'izdatelstvo-spektr',
            per_slug_default_publisher_ru([
                'meta_description_ru' => 'Издательство «Спектр»',
                'name_ru' => 'Спектр',
            ])
        );
        self::assertSame(
            'spectrum-publishing',
            per_slug_default_publisher_en([
                'meta_description_en' => 'Spectrum Publishing',
                'name_en' => 'Spectrum',
            ])
        );
        self::assertSame(
            'spektr',
            per_slug_default_publisher_ru([
                'meta_description_ru' => '',
                'name_ru' => 'Спектр',
            ])
        );
    }

    public function testUrlCatalogUsesEngPrefix(): void
    {
        self::assertSame('/ru/periodicals', per_pub_url_catalog(false));
        self::assertSame('/en/periodicals', per_pub_url_catalog(true));
    }

    public function testUrlPeriodicalBuildsSlugPath(): void
    {
        self::assertSame(
            '/ru/periodicals/spectr',
            per_pub_url_periodical(['id' => 6, 'slug_ru' => 'spectr', 'slug_en' => 'spectrum'], false)
        );
        self::assertSame(
            '/en/periodicals/spectrum',
            per_pub_url_periodical(['id' => 6, 'slug_ru' => 'spectr', 'slug_en' => 'spectrum'], true)
        );
    }
}
