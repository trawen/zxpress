<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once ZXPRESS_SITE_ROOT . '/includes/ezines_v2.php';

final class EzinesV2I18nTest extends TestCase
{
    public function testLangCodeNormalizesEng(): void
    {
        self::assertSame('en', ezines_v2_lang_code('eng'));
        self::assertSame('ru', ezines_v2_lang_code(''));
        self::assertSame('cs', ezines_v2_lang_code('cs'));
    }

    public function testI18nPickFallsBackToOriginalThenRu(): void
    {
        $byLang = [
            'sk' => ['title' => 'Slovensky'],
            'ru' => ['title' => 'Русский'],
            'en' => ['title' => 'English'],
        ];
        self::assertSame('Slovensky', ezines_v2_i18n_pick($byLang, 'sk', 'title', 'sk'));
        self::assertSame('Slovensky', ezines_v2_i18n_pick($byLang, 'cs', 'title', 'sk'));
        self::assertSame('English', ezines_v2_i18n_pick($byLang, 'en', 'title', 'sk'));
        self::assertSame('Русский', ezines_v2_i18n_pick($byLang, 'xx', 'title', 'ru'));
    }

    public function testFormatRoundTrip(): void
    {
        self::assertSame('markdown', ezines_v2_format_from_text_type(EZN_TEXT_TYPE_MARKDOWN));
        self::assertSame(EZN_TEXT_TYPE_HTML_PRE, ezines_v2_text_type_from_format('html_pre'));
        self::assertSame(EZN_TEXT_TYPE_TEXT_PRE, ezines_v2_text_type_from_format('text_pre'));
    }

    public function testPickPrimaryBodyPrefersWantThenOriginal(): void
    {
        $bodies = [
            ['lang' => 'ru', 'format' => 'html_pre', 'body' => 'RU', 'is_primary' => 1],
            ['lang' => 'sk', 'format' => 'html_pre', 'body' => 'SK', 'is_primary' => 1],
            ['lang' => 'en', 'format' => 'html_pre', 'body' => 'EN', 'is_primary' => 1],
        ];
        $picked = ezines_v2_pick_primary_body($bodies, 'en', 'sk');
        self::assertNotNull($picked);
        self::assertSame('en', $picked['lang']);
        self::assertSame('EN', $picked['body']);

        $pickedCs = ezines_v2_pick_primary_body($bodies, 'cs', 'sk');
        self::assertNotNull($pickedCs);
        self::assertSame('sk', $pickedCs['lang']);
    }
}
