<?php

const PER_ISSUE_FILES_ENTITY_TYPE = 3;

function per_issue_file_format_options(): array
{
    return [
        0 => 'SCL/TRD',
        2 => 'FDI',
        3 => 'UDI',
        4 => 'TD0',
        5 => 'TAP',
        6 => 'TZX',
        7 => 'PDF',
        8 => 'TXT',
        9 => 'HTML',
        10 => 'DJVU',
    ];
}

function per_issue_format_from_extension(string $ext): int
{
    $ext = strtolower(ltrim(trim($ext), '.'));
    $map = [
        'scl' => 0,
        'trd' => 0,
        'fdi' => 2,
        'udi' => 3,
        'td0' => 4,
        'tap' => 5,
        'tzx' => 6,
        'pdf' => 7,
        'txt' => 8,
        'html' => 9,
        'htm' => 9,
        'djvu' => 10,
        'djv' => 10,
    ];

    return $map[$ext] ?? 0;
}

function per_issue_resolve_upload_format(int $selectedFormat, string $ext): int
{
    if ($selectedFormat >= 0) {
        return max(0, min(255, $selectedFormat));
    }

    return per_issue_format_from_extension($ext);
}

function per_issue_post_upload_format(): int
{
    if (!array_key_exists('upload_issue_file_format', $_POST)) {
        return -1;
    }

    $raw = trim((string) $_POST['upload_issue_file_format']);
    if ($raw === '') {
        return -1;
    }

    return max(0, min(255, (int) $raw));
}

function per_issue_translit(string $text): string
{
    $text = mb_strtolower(trim($text), 'UTF-8');
    if ($text === '') {
        return '';
    }

    $map = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e',
        'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm',
        'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
        'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sh', 'ъ' => '',
        'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
    ];

    $out = strtr($text, $map);
    $out = preg_replace('/[^a-z0-9._-]+/i', '-', $out) ?? '';
    $out = trim($out, '-');

    return $out;
}

function per_issue_slug_part(string $text): string
{
    $slug = per_issue_translit($text);
    if ($slug === '') {
        return '';
    }

    return preg_replace('/-+/', '-', $slug) ?? $slug;
}

function per_issue_file_base_name(array $periodical, array $issue): string
{
    $title = trim((string) ($periodical['title_ru'] ?? ''));
    if ($title === '') {
        $title = trim((string) ($periodical['title_en'] ?? ''));
    }

    $parts = [];
    $titleSlug = per_issue_slug_part($title);
    if ($titleSlug !== '') {
        $parts[] = $titleSlug;
    }

    $year = trim((string) ($issue['issue_year'] ?? ''));
    if ($year !== '' && is_numeric($year)) {
        $parts[] = $year;
    }

    $issueNo = trim((string) ($issue['issue_no'] ?? ''));
    $issueNoSlug = per_issue_slug_part($issueNo);
    if ($issueNoSlug === '' && $issueNo !== '') {
        $issueNoSlug = preg_replace('/[^a-z0-9._-]+/i', '-', $issueNo) ?? 'issue';
        $issueNoSlug = trim($issueNoSlug, '-');
    }
    if ($issueNoSlug !== '') {
        $parts[] = $issueNoSlug;
    }

    if ($parts === []) {
        return 'issue-' . (int) ($issue['id'] ?? 0);
    }

    return implode('_', $parts);
}

function per_issue_sanitize_disk_name(string $name): string
{
    $name = basename(str_replace('\\', '/', trim($name)));
    if ($name === '' || $name === '.' || $name === '..') {
        return '';
    }

    return $name;
}

function per_issue_file_disk_path_by_name(string $name): string
{
    $safeName = per_issue_sanitize_disk_name($name);
    if ($safeName === '') {
        return '';
    }

    return zx_storage_path('content_files', $safeName);
}

function per_issue_file_public_url(string $name): string
{
    $safeName = per_issue_sanitize_disk_name($name);
    if ($safeName === '') {
        return '';
    }

    return '/content-files/' . rawurlencode($safeName);
}

function per_issue_storage_name_taken(mysqli $db, string $name): bool
{
    $diskPath = per_issue_file_disk_path_by_name($name);
    if ($diskPath !== '' && is_file($diskPath)) {
        return true;
    }

    $z = db_select(
        $db,
        'SELECT id FROM files_ WHERE entity_type=? AND name=? LIMIT 1',
        'is',
        PER_ISSUE_FILES_ENTITY_TYPE,
        $name
    );

    return (bool) ($z && mysqli_fetch_assoc($z));
}

function per_issue_make_file_name(mysqli $db, int $issueId, array $periodical, array $issue, string $ext): string
{
    $ext = strtolower(ltrim(trim($ext), '.'));
    if ($ext === '') {
        $ext = 'bin';
    }

    $base = per_issue_file_base_name($periodical, $issue);
    $name = $base . '.' . $ext;
    if (!per_issue_storage_name_taken($db, $name)) {
        return $name;
    }

    for ($n = 2; $n < 1000; $n++) {
        $candidate = $base . '_' . $n . '.' . $ext;
        if (!per_issue_storage_name_taken($db, $candidate)) {
            return $candidate;
        }
    }

    return $base . '_' . time() . '.' . $ext;
}

function per_issue_delete_file_record(mysqli $db, int $fileId, int $issueId): bool
{
    $z = db_select(
        $db,
        'SELECT id, name FROM files_ WHERE id=? AND entity_type=? AND entity_id=? LIMIT 1',
        'iii',
        $fileId,
        PER_ISSUE_FILES_ENTITY_TYPE,
        $issueId
    );
    $row = $z ? mysqli_fetch_assoc($z) : null;
    if (!$row) {
        return false;
    }

    $diskPath = per_issue_file_disk_path_by_name((string) ($row['name'] ?? ''));
    if ($diskPath !== '' && is_file($diskPath)) {
        @unlink($diskPath);
    }

    return db_exec($db, 'DELETE FROM files_ WHERE id=? AND entity_type=? AND entity_id=? LIMIT 1', 'iii', $fileId, PER_ISSUE_FILES_ENTITY_TYPE, $issueId);
}

function per_issue_format_size_display(int $size): string
{
    if ($size >= 1048576) {
        return round($size / 1048576, 1) . ' МБ';
    }
    if ($size >= 1024) {
        return round($size / 1024, 1) . ' КБ';
    }
    if ($size > 0) {
        return $size . ' Б';
    }

    return '';
}

function per_issue_load_files(mysqli $db, int $issueId): array
{
    if ($issueId <= 0) {
        return [];
    }

    $files = [];
    $z = db_select(
        $db,
        'SELECT * FROM files_ WHERE entity_type=? AND entity_id=? ORDER BY id ASC',
        'ii',
        PER_ISSUE_FILES_ENTITY_TYPE,
        $issueId
    );
    while ($z && ($f = mysqli_fetch_assoc($z))) {
        $fileId = (int) ($f['id'] ?? 0);
        if ($fileId <= 0) {
            continue;
        }

        $name = (string) ($f['name'] ?? '');
        $diskPath = per_issue_file_disk_path_by_name($name);
        if (($diskPath === '' || !is_file($diskPath)) && $name !== '') {
            $legacyExt = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if ($legacyExt === '') {
                $legacyExt = 'bin';
            }
            $legacyPath = zx_storage_path('content_files', $fileId . '.' . $legacyExt);
            $targetPath = per_issue_file_disk_path_by_name($name);
            if ($targetPath !== '' && is_file($legacyPath) && !is_file($targetPath)) {
                @rename($legacyPath, $targetPath);
                $diskPath = $targetPath;
            }
        }
        $f['file_url'] = ($diskPath !== '' && is_file($diskPath)) ? per_issue_file_public_url($name) : '';
        $f['size_display'] = per_issue_format_size_display((int) ($f['size'] ?? 0));
        $files[] = $f;
    }

    return $files;
}

function per_issue_sync_file_formats_from_post(mysqli $db, int $issueId): void
{
    $z = db_select(
        $db,
        'SELECT id FROM files_ WHERE entity_type=? AND entity_id=? ORDER BY id ASC',
        'ii',
        PER_ISSUE_FILES_ENTITY_TYPE,
        $issueId
    );
    while ($z && ($row = mysqli_fetch_assoc($z))) {
        $fileId = (int) ($row['id'] ?? 0);
        if ($fileId <= 0) {
            continue;
        }
        $key = 'issue_file_format_' . $fileId;
        if (!array_key_exists($key, $_POST)) {
            continue;
        }
        $format = max(0, min(255, (int) $_POST[$key]));
        db_exec(
            $db,
            'UPDATE files_ SET format=? WHERE id=? AND entity_type=? AND entity_id=? LIMIT 1',
            'iiii',
            $format,
            $fileId,
            PER_ISSUE_FILES_ENTITY_TYPE,
            $issueId
        );
    }
}

function per_issue_upload_files(
    mysqli $db,
    int $issueId,
    array $periodical,
    array $issue,
    int $selectedFormat
): bool {
    $upl = (isset($_FILES['upload_issue_files']) && is_array($_FILES['upload_issue_files'])) ? $_FILES['upload_issue_files'] : [];
    $names = (isset($upl['name']) && is_array($upl['name'])) ? $upl['name'] : [];
    $tmps = (isset($upl['tmp_name']) && is_array($upl['tmp_name'])) ? $upl['tmp_name'] : [];
    $sizes = (isset($upl['size']) && is_array($upl['size'])) ? $upl['size'] : [];

    for ($i = 0; $i < count($names); $i++) {
        $tmp = (string) ($tmps[$i] ?? '');
        $origName = (string) ($names[$i] ?? '');
        $fileSize = (int) ($sizes[$i] ?? 0);
        if ($tmp === '' || !is_uploaded_file($tmp) || $origName === '') {
            continue;
        }

        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if ($ext === '') {
            $ext = 'bin';
        }

        $displayName = per_issue_make_file_name($db, $issueId, $periodical, $issue, $ext);
        $fileFormat = per_issue_resolve_upload_format($selectedFormat, $ext);
        $saved = db_exec(
            $db,
            'INSERT INTO files_ (entity_type, entity_id, name, format, size, is_active) VALUES (?,?,?,?,?,1)',
            'iisii',
            PER_ISSUE_FILES_ENTITY_TYPE,
            $issueId,
            $displayName,
            $fileFormat,
            max(0, $fileSize)
        );
        if (!$saved) {
            return false;
        }

        $fileId = (int) mysqli_insert_id($db);
        if ($fileId <= 0) {
            return false;
        }

        if (!zx_storage_copy_uploaded_file('content_files', $displayName, $tmp)) {
            db_exec($db, 'DELETE FROM files_ WHERE id=? LIMIT 1', 'i', $fileId);
            return false;
        }
    }

    return true;
}
