<?php
/**
 * Admin helpers: RU → EN via Google Translate (gtx, same as tools/fill-articles-*.py).
 */

function admin_translate_has_cyrillic(string $text): bool
{
    return (bool) preg_match('/[А-Яа-яЁё]/u', $text);
}

/**
 * @throws RuntimeException
 */
function admin_translate_google_chunk(string $text, string $sl = 'ru', string $tl = 'en'): string
{
    if ($text === '') {
        return '';
    }
    if ($sl === 'ru' && !admin_translate_has_cyrillic($text)) {
        return $text;
    }

    $url = 'https://translate.googleapis.com/translate_a/single?client=gtx&sl='
        . rawurlencode($sl) . '&tl=' . rawurlencode($tl) . '&dt=t&q=' . rawurlencode($text);

    $ctx = stream_context_create([
        'http' => [
            'timeout' => 90,
            'header' => "User-Agent: zxpress-admin-translate/1.0\r\n",
        ],
    ]);

    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) {
        throw new RuntimeException('Не удалось связаться с сервисом перевода');
    }

    $data = json_decode($raw, true);
    if (!is_array($data) || !isset($data[0]) || !is_array($data[0])) {
        throw new RuntimeException('Некорректный ответ сервиса перевода');
    }

    $parts = [];
    foreach ($data[0] as $chunk) {
        if (is_array($chunk) && isset($chunk[0]) && $chunk[0] !== '') {
            $parts[] = (string) $chunk[0];
        }
    }
    $out = implode('', $parts);
    if (trim($out) === '' && trim($text) !== '') {
        throw new RuntimeException('Пустой перевод');
    }

    return $out;
}

/**
 * Split long HTML/text for translate API limits.
 *
 * @return list<string>
 */
function admin_translate_split_chunks(string $text, int $limit = 1400): array
{
    $len = strlen($text);
    if ($len <= $limit) {
        return [$text];
    }

    $chunks = [];
    $i = 0;
    while ($i < $len) {
        if ($len - $i <= $limit) {
            $chunks[] = substr($text, $i);
            break;
        }
        $end = $i + $limit;
        $start = $i + (int) ($limit / 3);
        $tag = strrpos($text, '>', $start);
        if ($tag !== false && $tag < $end) {
            $end = $tag + 1;
        } else {
            $nl = strrpos($text, "\n", $start);
            if ($nl !== false && $nl < $end) {
                $end = $nl + 1;
            } else {
                $sp = strrpos($text, ' ', $start);
                if ($sp !== false && $sp < $end) {
                    $end = $sp + 1;
                }
            }
        }
        $chunks[] = substr($text, $i, $end - $i);
        $i = $end;
    }

    return $chunks;
}

/**
 * @throws RuntimeException
 */
function admin_translate_text(string $text): string
{
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    if ($text === '' || !admin_translate_has_cyrillic($text)) {
        return $text;
    }

    $out = '';
    foreach (admin_translate_split_chunks($text) as $chunk) {
        $out .= admin_translate_google_chunk($chunk);
    }

    return $out;
}

function admin_translate_title(string $title): string
{
    $title = trim($title);
    if ($title === '') {
        return '';
    }
    $translated = admin_translate_text($title);
    if (strlen($translated) > 1024) {
        $translated = substr($translated, 0, 1024);
    }

    return $translated;
}
