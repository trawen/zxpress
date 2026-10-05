<?php

/**
 * Letter scan OCR via OdiRouter (Gemini vision).
 * Used by admin_letters.php?action=ocr
 *
 * PHP runs on an internal Docker network — default URL goes through nginx
 * (/internal/odirouter/…), same pattern as admin_translate.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/storage_paths.php';

const LETTERS_OCR_MODEL_DEFAULT = 'gemini-3.8-flash';
const LETTERS_OCR_MAX_EDGE = 1800;
const LETTERS_OCR_JPEG_QUALITY = 82;
const LETTERS_OCR_MAX_TOKENS = 16384;
const LETTERS_OCR_TIMEOUT_SEC = 120;

function letters_ocr_prompt(): string
{
    return <<<'PROMPT'
Ты обрабатываешь сканы старых писем (сцена ZX Spectrum, 90-е и 2000-е).
Если приложено несколько изображений — это страницы ОДНОГО письма (в порядке page1, page2, …). Читай их подряд как единый текст.

Верни ТОЛЬКО валидный JSON-объект (без markdown-ограждений ```) со полями:
{
  "body_ru": "распознанный текст RU",
  "body_en": "полный перевод EN",
  "summary_ru": "саммери RU 300–500 символов",
  "summary_en": "summary EN 300–500 chars",
  "meta_description_ru": "meta RU ≤155 символов",
  "meta_description_en": "meta EN ≤155 chars",
  "title_ru": "лучший заголовок RU",
  "title_en": "лучший title EN ≤60 символов",
  "date": "дд.мм.гггг или пустая строка",
  "from_nick": "ник/имя отправителя или пустая строка",
  "to_nick": "ник/имя адресата или пустая строка",
  "note": "одна строка про неуверенное чтение или пустая строка"
}

1) body_ru — дословно, ники и названия программ сохранить.
   - Склей переносы слов через дефис в конце строки: «пос-»+«мотрел» → «посмотрел». Настоящие дефисы (Rulez-z-z, BK-шки) оставляй.
   - Не копируй построчную вёрстку скана. Разбей на абзацы по смыслу (приветствие, основной текст, просьбы, подпись/дата). Абзацы разделяй \n\n.
   - Сомнительные места помечай (?), ничего не выдумывай.

2) body_en — полный перевод. Ники, названия программ и групп не переводи; города и имена транслитерируй. Те же абзацы.

3) summary_ru / summary_en:
   - Длина каждого саммери: 300–500 символов (не короче 300 и не длиннее 500).
   - Начинай с подлежащего: кто отправитель (по подписи/нику) и что он делает/пишет. Не пиши «автор письма».
   - Пиши в настоящем времени (как будто письмо читают сейчас): «присылает», «предлагает», «спрашивает», не «прислал»/«предложил».
   - Развёрнутая суть письма: что отправлено/предложено/спрашивают/просят, важные детали (программы, диски, города, условия обмена), без воды.
   - Без приветствий, прощаний, пожеланий удачи и без домыслов о ролях.
   - Не заканчивай и не добавляй в конец клише вроде «передаёт приветы», «передаёт привет», «ждёт ответа», «ждёт ответ», «надеется на ответ», «просит ответить», «пишет с уважением» — даже если так есть в письме. Саммери обрывай на содержательной сути.

4) meta_description_* — одно предложение ~до 155 символов, с ключевыми словами (ZX Spectrum, город, год, суть).

5) title_* — точный предмет письма, не общая тема. Английский ≤60 символов. Выбери один лучший вариант.

6) date — дата из письма в формате дд.мм.гггг, иначе "".

Отвечай сразу JSON-ом, без оправданий.
PROMPT;
}

/**
 * @return array{0:int,1:int}|null [w,h]
 */
function letters_ocr_image_size(string $path): ?array
{
    $info = @getimagesize($path);
    if (!$info || empty($info[0]) || empty($info[1])) {
        return null;
    }

    return [(int) $info[0], (int) $info[1]];
}

/**
 * Resize/recompress scan for vision API. Returns path to JPEG (may be tmp).
 * Caller must unlink prepared paths that differ from the upload source.
 */
function letters_ocr_prepare_jpeg(string $srcPath, string $mime): string
{
    $size = letters_ocr_image_size($srcPath);
    if ($size === null) {
        throw new RuntimeException('Не удалось прочитать изображение');
    }
    [$w, $h] = $size;
    $maxEdge = LETTERS_OCR_MAX_EDGE;
    $scale = 1.0;
    $long = max($w, $h);
    if ($long > $maxEdge) {
        $scale = $maxEdge / $long;
    }
    $nw = max(1, (int) round($w * $scale));
    $nh = max(1, (int) round($h * $scale));

    $src = null;
    if ($mime === 'image/jpeg' || $mime === 'image/jpg') {
        $src = @imagecreatefromjpeg($srcPath);
    } elseif ($mime === 'image/png') {
        $src = @imagecreatefrompng($srcPath);
    } elseif ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) {
        $src = @imagecreatefromwebp($srcPath);
    } elseif ($mime === 'image/gif' && function_exists('imagecreatefromgif')) {
        $src = @imagecreatefromgif($srcPath);
    }
    if (!$src) {
        throw new RuntimeException('GD не смог открыть изображение (' . $mime . ')');
    }

    $dst = imagecreatetruecolor($nw, $nh);
    if (!$dst) {
        imagedestroy($src);
        throw new RuntimeException('GD: imagecreatetruecolor failed');
    }
    $bg = imagecolorallocate($dst, 255, 255, 255);
    if ($bg !== false) {
        imagefilledrectangle($dst, 0, 0, $nw, $nh, $bg);
    }
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagedestroy($src);

    $tmpDir = function_exists('zx_tmp_root') ? zx_tmp_root() : sys_get_temp_dir();
    if ($tmpDir === '' || !is_dir($tmpDir) || !is_writable($tmpDir)) {
        imagedestroy($dst);
        throw new RuntimeException('OCR tmp dir недоступен: ' . $tmpDir);
    }
    $tmp = tempnam($tmpDir, 'locr');
    if ($tmp === false) {
        imagedestroy($dst);
        throw new RuntimeException('tempnam failed in ' . $tmpDir);
    }
    $out = $tmp . '.jpg';
    @unlink($tmp);
    $ok = @imagejpeg($dst, $out, LETTERS_OCR_JPEG_QUALITY);
    imagedestroy($dst);
    if (!$ok || !is_readable($out)) {
        @unlink($out);
        throw new RuntimeException('Не удалось сохранить JPEG для OCR');
    }

    return $out;
}

/**
 * @param list<array{path:string,mime:string,name?:string}> $images
 * @return array{
 *   body_ru:string,body_en:string,summary_ru:string,summary_en:string,
 *   meta_description_ru:string,meta_description_en:string,
 *   title_ru:string,title_en:string,date:string,from_nick:string,to_nick:string,note:string,
 *   model:string,usage:?array
 * }
 */
function letters_ocr_analyze(array $images): array
{
    if ($images === []) {
        throw new InvalidArgumentException('Нет изображений для OCR');
    }

    $apiKey = trim((string) (getenv('ODIROUTER_API_KEY') ?: ''));
    if ($apiKey === '') {
        throw new RuntimeException('ODIROUTER_API_KEY не задан в окружении PHP');
    }
    // Prefer in-cluster nginx proxy; override with ODIROUTER_BASE_URL if needed.
    $baseUrl = rtrim((string) (getenv('ODIROUTER_BASE_URL') ?: 'http://nginx/internal/odirouter/v1'), '/');
    $model = trim((string) (getenv('ODIROUTER_MODEL_LETTER') ?: LETTERS_OCR_MODEL_DEFAULT));
    if ($model === '') {
        $model = LETTERS_OCR_MODEL_DEFAULT;
    }

    $content = [
        ['type' => 'text', 'text' => letters_ocr_prompt()],
    ];
    $tmpToClean = [];

    try {
        foreach ($images as $img) {
            $path = (string) ($img['path'] ?? '');
            $mime = (string) ($img['mime'] ?? 'image/jpeg');
            if ($path === '' || !is_readable($path)) {
                throw new RuntimeException('Файл недоступен: ' . ($img['name'] ?? $path));
            }
            $prepared = letters_ocr_prepare_jpeg($path, $mime);
            if ($prepared !== $path) {
                $tmpToClean[] = $prepared;
            }
            $bin = file_get_contents($prepared);
            if ($bin === false || $bin === '') {
                throw new RuntimeException('Пустой файл после подготовки');
            }
            $content[] = [
                'type' => 'image',
                'source' => [
                    'type' => 'base64',
                    'media_type' => 'image/jpeg',
                    'data' => base64_encode($bin),
                ],
            ];
        }

        $payload = [
            'model' => $model,
            'max_tokens' => LETTERS_OCR_MAX_TOKENS,
            'temperature' => 0.2,
            'messages' => [
                ['role' => 'user', 'content' => $content],
            ],
        ];

        $ch = curl_init($baseUrl . '/messages');
        if ($ch === false) {
            throw new RuntimeException('curl_init failed');
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => LETTERS_OCR_TIMEOUT_SEC,
            CURLOPT_CONNECTTIMEOUT => 20,
        ]);
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $err = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            throw new RuntimeException('OCR network error: ' . $err);
        }
        if (!is_string($raw) || $raw === '') {
            throw new RuntimeException('OCR: пустой ответ HTTP ' . $status);
        }
        $data = json_decode($raw, true);
        if ($status < 200 || $status >= 300) {
            $msg = is_array($data)
                ? (string) ($data['error']['message'] ?? $data['message'] ?? substr($raw, 0, 400))
                : substr($raw, 0, 400);
            throw new RuntimeException('OCR HTTP ' . $status . ': ' . $msg);
        }

        $text = letters_ocr_extract_assistant_text(is_array($data) ? $data : []);
        $parsed = letters_ocr_parse_json_payload($text);
        $parsed['model'] = $model;
        $parsed['usage'] = is_array($data['usage'] ?? null) ? $data['usage'] : null;

        return $parsed;
    } finally {
        foreach ($tmpToClean as $p) {
            @unlink($p);
        }
    }
}

/**
 * @param array<string,mixed> $data
 */
function letters_ocr_extract_assistant_text(array $data): string
{
    $parts = [];
    if (isset($data['content']) && is_array($data['content'])) {
        foreach ($data['content'] as $block) {
            if (!is_array($block)) {
                continue;
            }
            if (($block['type'] ?? '') === 'text' && isset($block['text'])) {
                $parts[] = (string) $block['text'];
            }
        }
    }
    if ($parts === [] && isset($data['output_text']) && is_string($data['output_text'])) {
        $parts[] = $data['output_text'];
    }

    return trim(implode("\n", $parts));
}

/**
 * @return array{
 *   body_ru:string,body_en:string,summary_ru:string,summary_en:string,
 *   meta_description_ru:string,meta_description_en:string,
 *   title_ru:string,title_en:string,date:string,from_nick:string,to_nick:string,note:string
 * }
 */
function letters_ocr_parse_json_payload(string $text): array
{
    $text = trim($text);
    if ($text === '') {
        throw new RuntimeException('Модель вернула пустой текст');
    }
    // Strip optional ```json fences
    if (preg_match('/^```(?:json)?\s*([\s\S]*?)\s*```$/u', $text, $m)) {
        $text = trim($m[1]);
    }
    $start = strpos($text, '{');
    $end = strrpos($text, '}');
    if ($start === false || $end === false || $end < $start) {
        throw new RuntimeException('В ответе модели нет JSON-объекта');
    }
    $json = substr($text, $start, $end - $start + 1);
    $data = json_decode($json, true);
    if (!is_array($data)) {
        throw new RuntimeException('Не удалось разобрать JSON ответа модели');
    }

    $pick = static function (array $d, string $key): string {
        $v = $d[$key] ?? '';
        if (!is_string($v) && !is_numeric($v)) {
            return '';
        }

        return trim((string) $v);
    };

    return [
        'body_ru' => $pick($data, 'body_ru'),
        'body_en' => $pick($data, 'body_en'),
        'summary_ru' => letters_ocr_strip_summary_closing($pick($data, 'summary_ru')),
        'summary_en' => letters_ocr_strip_summary_closing($pick($data, 'summary_en')),
        'meta_description_ru' => $pick($data, 'meta_description_ru'),
        'meta_description_en' => $pick($data, 'meta_description_en'),
        'title_ru' => $pick($data, 'title_ru'),
        'title_en' => $pick($data, 'title_en'),
        'date' => $pick($data, 'date'),
        'from_nick' => $pick($data, 'from_nick'),
        'to_nick' => $pick($data, 'to_nick'),
        'note' => $pick($data, 'note'),
    ];
}

/**
 * Drop trailing greeting/closing fluff often appended to OCR summaries.
 */
function letters_ocr_strip_summary_closing(string $text): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }

    $patterns = [
        // RU: «… а также передаёт приветы и ждёт ответ.»
        '/(?:[.,;:\s]+|\s+)(?:а\s+также\s+)?передаёт\s+привет(?:ы|ик)?(?:\s+и\s+ждёт\s+ответ(?:а)?)?[.!]?\s*$/iu',
        '/(?:[.,;:\s]+|\s+)ждёт\s+ответ(?:а)?[.!]?\s*$/iu',
        '/(?:[.,;:\s]+|\s+)надеется\s+на\s+ответ[.!]?\s*$/iu',
        '/(?:[.,;:\s]+|\s+)просит\s+ответить[.!]?\s*$/iu',
        // EN equivalents
        '/(?:[.,;:\s]+|\s+)(?:and\s+)?(?:also\s+)?sends?\s+(?:his\s+|her\s+|their\s+)?regards?(?:\s+and\s+(?:awaits?|waits?\s+for)\s+(?:a\s+)?reply)?[.!]?\s*$/i',
        '/(?:[.,;:\s]+|\s+)(?:and\s+)?(?:awaits?|waits?\s+for)\s+(?:a\s+)?reply[.!]?\s*$/i',
        '/(?:[.,;:\s]+|\s+)(?:and\s+)?hopes?\s+for\s+(?:a\s+)?reply[.!]?\s*$/i',
    ];

    $prev = null;
    while ($prev !== $text) {
        $prev = $text;
        foreach ($patterns as $re) {
            $text = trim((string) preg_replace($re, '', $text));
        }
        $text = rtrim($text, " \t.,;");
    }

    return $text;
}

/**
 * Try match author nickname (case-insensitive) → id.
 *
 * @param list<array{id:int|string,nickname?:string}> $authors
 */
function letters_ocr_match_author_id(array $authors, string $nick): int
{
    $nick = trim($nick);
    if ($nick === '') {
        return 0;
    }
    $needle = mb_strtolower($nick);
    foreach ($authors as $a) {
        $n = mb_strtolower(trim((string) ($a['nickname'] ?? '')));
        if ($n !== '' && ($n === $needle || str_contains($n, $needle) || str_contains($needle, $n))) {
            return (int) ($a['id'] ?? 0);
        }
    }

    return 0;
}
