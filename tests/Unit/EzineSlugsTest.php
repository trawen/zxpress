<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once ZXPRESS_SITE_ROOT . '/includes/periodical_issue_files.php';
require_once ZXPRESS_SITE_ROOT . '/includes/periodicals_slugs.php';
require_once ZXPRESS_SITE_ROOT . '/includes/ezine_slugs.php';

final class EzineSlugsTest extends TestCase
{
    public function testTruncateArticleSlugAtWordBoundary(): void
    {
        $slug = str_repeat('word-', 30) . 'tail';
        $truncated = ezn_truncate_article_slug($slug);

        self::assertLessThanOrEqual(EZN_ARTICLE_SLUG_MAX_LEN, strlen($truncated));
        self::assertStringEndsNotWith('-', $truncated);
        self::assertSame(str_repeat('word-', 19) . 'word', $truncated);
    }

    public function testTruncateArticleSlugKeepsShortSlug(): void
    {
        self::assertSame('short-slug', ezn_truncate_article_slug('short-slug'));
    }

    public function testDefaultArticleSlugIsTruncated(): void
    {
        $meta = implode(' ', array_fill(0, 40, 'длинное-слово'));
        $slug = ezn_default_article_ru([
            'meta_description_ru' => $meta,
            'title' => 'Заголовок',
        ]);

        self::assertLessThanOrEqual(EZN_ARTICLE_SLUG_MAX_LEN, strlen($slug));
        self::assertNotSame('', $slug);
    }
}
