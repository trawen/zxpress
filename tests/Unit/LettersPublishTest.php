<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once ZXPRESS_SITE_ROOT . '/includes/letters_publish.php';

final class LettersPublishTest extends TestCase
{
    public function testDayStartIsMoscowMidnight(): void
    {
        $tz = new DateTimeZone(LETTERS_PUBLISH_TZ);
        $start = letters_publish_day_start($tz);
        $parsed = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $start, $tz);

        self::assertNotFalse($parsed);
        self::assertSame('00:00:00', $parsed->format('H:i:s'));
        self::assertSame((new DateTimeImmutable('now', $tz))->format('Y-m-d'), $parsed->format('Y-m-d'));
    }

    public function testPublishNowUsesMoscowTimezone(): void
    {
        $tz = new DateTimeZone(LETTERS_PUBLISH_TZ);
        $now = letters_publish_now($tz);
        $parsed = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $now, $tz);
        $ref = new DateTimeImmutable('now', $tz);

        self::assertNotFalse($parsed);
        self::assertLessThanOrEqual(2, abs($parsed->getTimestamp() - $ref->getTimestamp()));
    }

    public function testQueuedStatusKeepsExistingQueuedAt(): void
    {
        $fields = letters_publish_apply_status(LETTER_STATUS_QUEUED, [
            'publish_status' => LETTER_STATUS_QUEUED,
            'queued_at' => '2026-09-01 10:00:00',
        ]);

        self::assertSame(LETTER_STATUS_QUEUED, $fields['publish_status']);
        self::assertSame(0, $fields['is_active']);
        self::assertSame('2026-09-01 10:00:00', $fields['queued_at']);
        self::assertNull($fields['published_at']);
    }

    public function testPublishedStatusSetsActiveAndTimestamp(): void
    {
        $fields = letters_publish_apply_status(LETTER_STATUS_PUBLISHED, [
            'publish_status' => LETTER_STATUS_QUEUED,
            'queued_at' => '2026-09-01 10:00:00',
        ]);

        self::assertSame(LETTER_STATUS_PUBLISHED, $fields['publish_status']);
        self::assertSame(1, $fields['is_active']);
        self::assertNull($fields['queued_at']);
        self::assertNotNull($fields['published_at']);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $fields['published_at']);
    }
}
