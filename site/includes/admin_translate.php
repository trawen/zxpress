<?php
/**
 * Admin helpers: RU → EN via Google Translate (gtx, same as tools/fill-articles-*.py).
 * PHP runs on an internal Docker network — outbound HTTPS goes through nginx proxy.
 */

function admin_translate_has_cyrillic(string $text): bool
{
    return (bool) preg_match('/[А-Яа-яЁё]/u', $text);
}

function admin_translate_normalize_input(string $text): string
{
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    if ($text === '') {
        return '';
    }

    if (!mb_check_encoding($text, 'UTF-8')) {
        $converted = @iconv('CP1251', 'UTF-8//IGNORE', $text);
        if (is_string($converted) && $converted !== '') {
            $text = $converted;
        }
    }

    if (strpos($text, '&') !== false) {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    return $text;
}

/**
 * @throws RuntimeException
 */
function admin_translate_http_post(string $url, string $body, int $timeout = 90): string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
                'User-Agent: zxpress-admin-translate/1.0',
            ],
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('HTTP: ' . $err);
        }
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code !== 200) {
            throw new RuntimeException('HTTP ' . $code);
        }

        return $raw;
    }

    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded; charset=UTF-8\r\n"
                . "User-Agent: zxpress-admin-translate/1.0\r\n",
            'content' => $body,
            'timeout' => $timeout,
        ],
    ]);

    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) {
        $err = error_get_last();
        $msg = is_array($err) && isset($err['message']) ? $err['message'] : 'unknown error';
        throw new RuntimeException('HTTP: ' . $msg);
    }

    return $raw;
}

function admin_translate_is_valid_response(string $raw): bool
{
    if ($raw === '' || $raw[0] !== '[') {
        return false;
    }
    $data = json_decode($raw, true);

    return is_array($data) && isset($data[0]) && is_array($data[0]);
}

/**
 * @throws RuntimeException
 */
function admin_translate_fetch(string $text, string $sl = 'auto', string $tl = 'en'): string
{
    $body = http_build_query([
        'client' => 'gtx',
        'sl' => $sl,
        'tl' => $tl,
        'dt' => 't',
        'q' => $text,
    ], '', '&', PHP_QUERY_RFC3986);

    $targets = [
        'http://nginx/internal/translate-google',
        'https://translate.googleapis.com/translate_a/single',
    ];

    $lastError = 'unknown error';
    foreach ($targets as $url) {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                $raw = admin_translate_http_post($url, $body);
                if (admin_translate_is_valid_response($raw)) {
                    return $raw;
                }
                $lastError = 'invalid JSON from ' . parse_url($url, PHP_URL_HOST);
            } catch (RuntimeException $e) {
                $lastError = $e->getMessage();
            }
            if ($attempt < 2) {
                usleep(400000 * ($attempt + 1));
            }
        }
    }

    throw new RuntimeException('Не удалось связаться с сервисом перевода: ' . $lastError);
}

/**
 * @throws RuntimeException
 */
function admin_translate_google_chunk(string $text, string $sl = 'auto', string $tl = 'en'): string
{
    if ($text === '') {
        return '';
    }
    if (!admin_translate_has_cyrillic($text)) {
        return $text;
    }

    $raw = admin_translate_fetch($text, $sl, $tl);
    $data = json_decode($raw, true);

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
 * Split long HTML/text for translate API limits (UTF-8 safe, byte-oriented).
 *
 * @return list<string>
 */
function admin_translate_split_chunks(string $text, int $limit = 4500): array
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

        $piece = mb_strcut($text, $i, $limit, 'UTF-8');
        if ($piece === false || $piece === '') {
            $piece = substr($text, $i, $limit);
        }
        $pieceLen = strlen($piece);
        if ($pieceLen <= 0) {
            break;
        }

        $end = $i + $pieceLen;
        $start = $i + (int) ($pieceLen / 3);
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

        $chunk = mb_strcut($text, $i, $end - $i, 'UTF-8');
        if ($chunk === false || $chunk === '') {
            $chunk = substr($text, $i, $end - $i);
        }
        if ($chunk === '') {
            break;
        }
        $chunks[] = $chunk;
        $i += strlen($chunk);
    }

    return $chunks;
}

/**
 * @throws RuntimeException
 */
function admin_translate_text(string $text): string
{
    $text = admin_translate_normalize_input($text);
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
    $title = admin_translate_normalize_input(trim($title));
    if ($title === '') {
        return '';
    }
    $translated = admin_translate_text($title);
    if (strlen($translated) > 1024) {
        $translated = substr($translated, 0, 1024);
    }

    return $translated;
}
