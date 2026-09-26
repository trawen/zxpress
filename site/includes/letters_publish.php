<?php

/** Letter publish_status values. */
const LETTER_STATUS_DRAFT = 0;
const LETTER_STATUS_QUEUED = 1;
const LETTER_STATUS_PUBLISHED = 2;
const LETTER_STATUS_DELETED = 3;

/** Calendar timezone for "one publish per day". */
const LETTERS_PUBLISH_TZ = 'Europe/Moscow';

function letters_publish_status_label(int $status): string
{
    return match ($status) {
        LETTER_STATUS_DRAFT => 'черновик',
        LETTER_STATUS_QUEUED => 'в очереди',
        LETTER_STATUS_PUBLISHED => 'опубликовано',
        LETTER_STATUS_DELETED => 'удалено',
        default => 'неизвестно',
    };
}

/**
 * Normalize admin form value to a known status.
 */
function letters_publish_status_from_input(string $raw): int
{
    $raw = trim($raw);
    if ($raw === 'draft' || $raw === (string) LETTER_STATUS_DRAFT) {
        return LETTER_STATUS_DRAFT;
    }
    if ($raw === 'queued' || $raw === (string) LETTER_STATUS_QUEUED) {
        return LETTER_STATUS_QUEUED;
    }
    if ($raw === 'published' || $raw === (string) LETTER_STATUS_PUBLISHED) {
        return LETTER_STATUS_PUBLISHED;
    }
    if ($raw === 'deleted' || $raw === (string) LETTER_STATUS_DELETED) {
        return LETTER_STATUS_DELETED;
    }

    return LETTER_STATUS_DRAFT;
}

/**
 * Derive is_active + timestamp fields for a status transition.
 *
 * @param array{publish_status?:int|string|null,queued_at?:string|null,published_at?:string|null,deleted_at?:string|null} $prev
 * @return array{publish_status:int,is_active:int,queued_at:?string,published_at:?string,deleted_at:?string}
 */
function letters_publish_apply_status(int $status, array $prev = []): array
{
    $now = letters_publish_now();
    $prevStatus = (int) ($prev['publish_status'] ?? LETTER_STATUS_DRAFT);
    $queuedAt = isset($prev['queued_at']) && $prev['queued_at'] !== '' && $prev['queued_at'] !== null
        ? (string) $prev['queued_at']
        : null;
    $publishedAt = isset($prev['published_at']) && $prev['published_at'] !== '' && $prev['published_at'] !== null
        ? (string) $prev['published_at']
        : null;
    $deletedAt = isset($prev['deleted_at']) && $prev['deleted_at'] !== '' && $prev['deleted_at'] !== null
        ? (string) $prev['deleted_at']
        : null;

    if ($status === LETTER_STATUS_PUBLISHED) {
        return [
            'publish_status' => LETTER_STATUS_PUBLISHED,
            'is_active' => 1,
            'queued_at' => null,
            'published_at' => $publishedAt ?? $now,
            'deleted_at' => null,
        ];
    }

    if ($status === LETTER_STATUS_QUEUED) {
        return [
            'publish_status' => LETTER_STATUS_QUEUED,
            'is_active' => 0,
            'queued_at' => ($prevStatus === LETTER_STATUS_QUEUED && $queuedAt !== null) ? $queuedAt : $now,
            'published_at' => null,
            'deleted_at' => null,
        ];
    }

    if ($status === LETTER_STATUS_DELETED) {
        return [
            'publish_status' => LETTER_STATUS_DELETED,
            'is_active' => 0,
            'queued_at' => null,
            'published_at' => null,
            'deleted_at' => $deletedAt ?? $now,
        ];
    }

    return [
        'publish_status' => LETTER_STATUS_DRAFT,
        'is_active' => 0,
        'queued_at' => null,
        'published_at' => null,
        'deleted_at' => null,
    ];
}

function letters_publish_tz(): DateTimeZone
{
    return new DateTimeZone(LETTERS_PUBLISH_TZ);
}

/** Current datetime in Europe/Moscow (naive DATETIME string for MySQL). */
function letters_publish_now(?DateTimeZone $tz = null): string
{
    $tz = $tz ?? letters_publish_tz();

    return (new DateTimeImmutable('now', $tz))->format('Y-m-d H:i:s');
}

function letters_publish_day_start(?DateTimeZone $tz = null): string
{
    $tz = $tz ?? letters_publish_tz();
    $dt = new DateTimeImmutable('now', $tz);
    $dt = $dt->setTime(0, 0, 0);

    return $dt->format('Y-m-d H:i:s');
}

/**
 * Publish at most one queued letter per calendar day (Europe/Moscow).
 * Serialized via GET_LOCK so concurrent snailmail/authors hits cannot each publish one.
 *
 * @return int|null published letter id, or null if nothing published
 */
function letters_maybe_publish_next(mysqli $db): ?int
{
    $lockName = 'zxpress_letters_daily_publish';
    $lockStmt = $db->prepare('SELECT GET_LOCK(?, 5)');
    if (!$lockStmt) {
        error_log('[letters_publish] prepare GET_LOCK failed: ' . $db->error);
        return null;
    }
    $lockStmt->bind_param('s', $lockName);
    $lockStmt->execute();
    $lockRow = $lockStmt->get_result()->fetch_row();
    $lockStmt->close();
    if ((int) ($lockRow[0] ?? 0) !== 1) {
        // Another request is publishing, or lock wait timed out — skip.
        return null;
    }

    try {
        if (!$db->begin_transaction()) {
            throw new RuntimeException('begin_transaction failed: ' . $db->error);
        }

        $dayStart = letters_publish_day_start();
        $dayEnd = (new DateTimeImmutable($dayStart, letters_publish_tz()))
            ->modify('+1 day')
            ->format('Y-m-d H:i:s');
        $statusPublished = LETTER_STATUS_PUBLISHED;

        // Any publish today (auto or manual) blocks another auto-publish.
        $stmtToday = $db->prepare(
            'SELECT id FROM letters WHERE publish_status = ? AND published_at IS NOT NULL '
            . 'AND published_at >= ? AND published_at < ? LIMIT 1'
        );
        if (!$stmtToday) {
            throw new RuntimeException('prepare today failed: ' . $db->error);
        }
        $stmtToday->bind_param('iss', $statusPublished, $dayStart, $dayEnd);
        $stmtToday->execute();
        $todayRow = $stmtToday->get_result()->fetch_assoc();
        $stmtToday->close();
        if ($todayRow) {
            $db->commit();
            return null;
        }

        $statusQueued = LETTER_STATUS_QUEUED;
        $stmtNext = $db->prepare(
            'SELECT id FROM letters WHERE publish_status = ? ORDER BY queued_at ASC, id ASC LIMIT 1 FOR UPDATE'
        );
        if (!$stmtNext) {
            throw new RuntimeException('prepare next failed: ' . $db->error);
        }
        $stmtNext->bind_param('i', $statusQueued);
        $stmtNext->execute();
        $nextRow = $stmtNext->get_result()->fetch_assoc();
        $stmtNext->close();
        if (!$nextRow) {
            $db->commit();
            return null;
        }

        $letterId = (int) ($nextRow['id'] ?? 0);
        if ($letterId <= 0) {
            $db->commit();
            return null;
        }

        $now = letters_publish_now();
        $stmtUp = $db->prepare(
            'UPDATE letters SET publish_status = ?, is_active = 1, published_at = ?, queued_at = NULL, deleted_at = NULL '
            . 'WHERE id = ? AND publish_status = ? LIMIT 1'
        );
        if (!$stmtUp) {
            throw new RuntimeException('prepare update failed: ' . $db->error);
        }
        $stmtUp->bind_param('isii', $statusPublished, $now, $letterId, $statusQueued);
        if (!$stmtUp->execute() || $stmtUp->affected_rows < 1) {
            $stmtUp->close();
            $db->commit();
            return null;
        }
        $stmtUp->close();
        $db->commit();

        return $letterId;
    } catch (Throwable $e) {
        @$db->rollback();
        error_log('[letters_publish] maybe_publish_next failed: ' . $e->getMessage());
        return null;
    } finally {
        $rel = $db->prepare('SELECT RELEASE_LOCK(?)');
        if ($rel) {
            $rel->bind_param('s', $lockName);
            $rel->execute();
            $rel->close();
        }
    }
}
