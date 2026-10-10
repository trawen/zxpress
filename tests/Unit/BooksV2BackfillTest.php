<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once ZXPRESS_SITE_ROOT . '/includes/books_v2.php';

final class BooksV2BackfillTest extends TestCase
{
    public function testParseAuthorsSplitsOnCommaAndSemicolon(): void
    {
        self::assertSame(
            ['Иванов И.И', 'Петров П.П'],
            books_v2_parse_authors('Иванов И.И., Петров П.П.')
        );
        self::assertSame(
            ['A', 'B', 'C'],
            books_v2_parse_authors('A; B, C')
        );
        self::assertSame([], books_v2_parse_authors('  '));
        self::assertSame(
            ['Один Автор'],
            books_v2_parse_authors('Один Автор')
        );
    }

    public function testParseAuthorsDedupesAndTrims(): void
    {
        self::assertSame(
            ['Foo'],
            books_v2_parse_authors('Foo, Foo, Foo.')
        );
    }

    public function testParseAuthorsSplitsCompactFioList(): void
    {
        $names = books_v2_parse_authors(
            'КОТОВ Юрий Владимирович ЛЮБУТОВ Олег Дмитриевич НАДЕЖИН Алексей Михайлович'
        );
        self::assertSame(
            [
                'КОТОВ Юрий Владимирович',
                'ЛЮБУТОВ Олег Дмитриевич',
                'НАДЕЖИН Алексей Михайлович',
            ],
            $names
        );
    }

    public function testIsbn10ConvertsToIsbn13(): void
    {
        // Valid ISBN-10 0-306-40615-2 → 9780306406157
        $norm = books_v2_normalize_isbn('0-306-40615-2');
        self::assertFalse($norm['is_invalid']);
        self::assertSame('9780306406157', $norm['isbn13']);
    }

    public function testIsbn13Checksum(): void
    {
        $ok = books_v2_normalize_isbn('978-0-306-40615-7');
        self::assertFalse($ok['is_invalid']);
        self::assertSame('9780306406157', $ok['isbn13']);

        $bad = books_v2_normalize_isbn('978-0-306-40615-8');
        self::assertTrue($bad['is_invalid']);
        self::assertSame('9780306406158', $bad['isbn13']);
    }

    public function testIsbnInvalidShort(): void
    {
        $norm = books_v2_normalize_isbn('0233-4844');
        self::assertTrue($norm['is_invalid']);
        self::assertNull($norm['isbn13']);
    }

    public function testContentEditionDepthRejectsChains(): void
    {
        $editions = [
            1 => ['content_edition_id' => null],
            2 => ['content_edition_id' => 1],
            3 => ['content_edition_id' => 2],
        ];
        self::assertTrue(books_v2_content_edition_depth_ok(null, $editions));
        self::assertTrue(books_v2_content_edition_depth_ok(1, $editions));
        self::assertFalse(books_v2_content_edition_depth_ok(2, $editions));
        self::assertFalse(books_v2_content_edition_depth_ok(99, $editions));
    }

    public function testSlugifyRespectsBooksMaxLen(): void
    {
        $long = str_repeat('абвгдеёжзийклмнопрстуфхцчшщъыьэюя ', 20);
        $slug = books_v2_slugify($long);
        self::assertNotSame('', $slug);
        self::assertLessThanOrEqual(BOOKS_V2_SLUG_MAX_LEN, strlen($slug));
    }

    public function testStripHtmlTitle(): void
    {
        self::assertSame(
            'Глава пятая - лишнее',
            books_v2_strip_html_title('<b>Глава пятая</b> - лишнее')
        );
    }

    public function testYearFromUnix(): void
    {
        self::assertSame(1995, books_v2_year_from_unix(gmmktime(0, 0, 0, 6, 1, 1995)));
        self::assertNull(books_v2_year_from_unix(0));
    }
}
