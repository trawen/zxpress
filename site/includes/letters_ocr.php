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
/** Max images per OdiRouter vision call — larger packs often 504 upstream. */
const LETTERS_OCR_BATCH_SIZE = 2;

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
   - Начинай коротко с ника отправителя и глагола: «Sector пишет…», «Maxc предлагает…». Не пиши «автор письма».
   - Запрещено в начале (и вообще в саммери раздувать так): группа отправителя («из Serious Speccy Group»), развёрнутый адресат («пишет Максиму (Unbeliever / Speed Co)»), полные ФИО/алиасы получателя — на сайте это и так видно. Достаточно «Sector пишет…».
   - Пиши в настоящем времени (как будто письмо читают сейчас): «присылает», «предлагает», «спрашивает», не «прислал»/«предложил».
   - Развёрнутая суть письма: что отправлено/предложено/спрашивают/просят, важные детали (программы, диски, города, условия обмена), без воды.
   - Без приветствий, прощаний, пожеланий удачи и без домыслов о ролях.
   - Не заканчивай и не добавляй в конец клише вроде «передаёт приветы», «передаёт привет», «ждёт ответа», «ждёт ответ», «надеется на ответ», «просит ответить», «пишет с уважением» — даже если так есть в письме. Саммери обрывай на содержательной сути.

4) meta_description_* — одно предложение ~до 155 символов, с ключевыми словами (ZX Spectrum, город, год, суть).

5) title_* — точный предмет письма (о чём речь: программа, диск, предложение обмена и т.п.), не общая тема.
   - Запрещено: «Письмо от X к Y», «Letter from X to Y», «Письмо Sector», любые формулировки с отправителем/адресатом — это и так видно на сайте.
   - Английский ≤60 символов. Выбери один лучший вариант.

6) date — дата из письма в формате дд.мм.гггг, иначе "".

Отвечай сразу JSON-ом, без оправданий.
PROMPT;
}

function letters_ocr_prompt_continuation(int $pageFrom, int $pageTo, int $pageTotal): string
{
    return <<<PROMPT
Ты продолжаешь OCR старого письма (ZX Spectrum, 90-е/2000-е).
Сейчас страницы {$pageFrom}–{$pageTo} из {$pageTotal} ОДНОГО письма. Не повторяй уже распознанный текст предыдущих страниц.

Верни ТОЛЬКО JSON:
{
  "body_ru": "текст только этих страниц RU",
  "body_en": "перевод только этих страниц EN",
  "summary_ru": "",
  "summary_en": "",
  "meta_description_ru": "",
  "meta_description_en": "",
  "title_ru": "",
  "title_en": "",
  "date": "",
  "from_nick": "",
  "to_nick": "",
  "note": "сомнения по чтению или пустая строка"
}

Правила body как обычно: дословно, склейка переносов через дефис, абзацы по смыслу через \\n\\n, (?) на сомнительном.
Отвечай сразу JSON-ом.
PROMPT;
}

function letters_ocr_prompt_finalize_meta(): string
{
    return <<<'PROMPT'
По полному тексту старого письма (ZX Spectrum) заполни метаданные.

Верни ТОЛЬКО JSON:
{
  "body_ru": "",
  "body_en": "",
  "summary_ru": "саммери RU 300–500 символов",
  "summary_en": "summary EN 300–500 chars",
  "meta_description_ru": "meta RU ≤155 символов",
  "meta_description_en": "meta EN ≤155 chars",
  "title_ru": "лучший заголовок RU",
  "title_en": "лучший title EN ≤60 символов",
  "date": "дд.мм.гггг или пустая строка",
  "from_nick": "ник отправителя или пустая строка",
  "to_nick": "ник адресата или пустая строка",
  "note": ""
}

Правила summary: настоящее время; начинай «Nick пишет/предлагает…» — только ник отправителя, без группы («из … Group») и без развёрнутого адресата («пишет Максиму (Unbeliever / …)»); без приветствий/прощаний и клише «передаёт приветы» / «ждёт ответ».
Правила title: предмет письма (программа/диск/обмен и т.п.), без «Письмо от X к Y» / «Letter from…» и без имён отправителя/адресата в заголовке.
Отвечай сразу JSON-ом.
PROMPT;
}

/**
 * Budget for multi-batch OCR (vision batches + optional meta finalize).
 */
function letters_ocr_wall_timeout_sec(int $imageCount): int
{
    $batchSize = max(1, LETTERS_OCR_BATCH_SIZE);
    $batches = (int) max(1, (int) ceil($imageCount / $batchSize));
    // Each vision batch up to TIMEOUT; +1 finalize; +60s slack.
    return ($batches + 1) * LETTERS_OCR_TIMEOUT_SEC + 60;
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
    $baseUrl = rtrim((string) (getenv('ODIROUTER_BASE_URL') ?: 'http://nginx/internal/odirouter/v1'), '/');
    $model = trim((string) (getenv('ODIROUTER_MODEL_LETTER') ?: LETTERS_OCR_MODEL_DEFAULT));
    if ($model === '') {
        $model = LETTERS_OCR_MODEL_DEFAULT;
    }
    $batchSize = (int) (getenv('ODIROUTER_LETTER_BATCH') ?: LETTERS_OCR_BATCH_SIZE);
    if ($batchSize < 1) {
        $batchSize = LETTERS_OCR_BATCH_SIZE;
    }

    $total = count($images);
    $batches = array_chunk($images, $batchSize);
    $merged = [
        'body_ru' => '',
        'body_en' => '',
        'summary_ru' => '',
        'summary_en' => '',
        'meta_description_ru' => '',
        'meta_description_en' => '',
        'title_ru' => '',
        'title_en' => '',
        'date' => '',
        'from_nick' => '',
        'to_nick' => '',
        'note' => '',
        'model' => $model,
        'usage' => null,
    ];
    $notes = [];
    $usageSum = ['input_tokens' => 0, 'output_tokens' => 0];
    $haveUsage = false;
    $pageCursor = 1;

    foreach ($batches as $batchIndex => $batch) {
        $pageFrom = $pageCursor;
        $pageTo = $pageCursor + count($batch) - 1;
        $pageCursor = $pageTo + 1;
        $isFirst = ($batchIndex === 0);
        $prompt = $isFirst
            ? letters_ocr_prompt()
            : letters_ocr_prompt_continuation($pageFrom, $pageTo, $total);

        $parsed = letters_ocr_request_vision($baseUrl, $apiKey, $model, $prompt, $batch);
        $merged['body_ru'] = letters_ocr_join_text($merged['body_ru'], (string) ($parsed['body_ru'] ?? ''));
        $merged['body_en'] = letters_ocr_join_text($merged['body_en'], (string) ($parsed['body_en'] ?? ''));
        foreach (['date', 'from_nick', 'to_nick', 'title_ru', 'title_en', 'summary_ru', 'summary_en', 'meta_description_ru', 'meta_description_en'] as $key) {
            if ($merged[$key] === '' && trim((string) ($parsed[$key] ?? '')) !== '') {
                $merged[$key] = trim((string) $parsed[$key]);
            }
        }
        $note = trim((string) ($parsed['note'] ?? ''));
        if ($note !== '') {
            $notes[] = $note;
        }
        if (is_array($parsed['usage'] ?? null)) {
            $haveUsage = true;
            $usageSum['input_tokens'] += (int) ($parsed['usage']['input_tokens'] ?? $parsed['usage']['prompt_tokens'] ?? 0);
            $usageSum['output_tokens'] += (int) ($parsed['usage']['output_tokens'] ?? $parsed['usage']['completion_tokens'] ?? 0);
        }
    }

    // Multi-batch: rebuild titles/summaries from the full recognized text (text-only, cheap).
    if (count($batches) > 1 && trim($merged['body_ru']) !== '') {
        try {
            $meta = letters_ocr_request_text_meta($baseUrl, $apiKey, $model, $merged['body_ru'], $merged['body_en']);
            foreach (['summary_ru', 'summary_en', 'meta_description_ru', 'meta_description_en', 'title_ru', 'title_en', 'date', 'from_nick', 'to_nick'] as $key) {
                $v = trim((string) ($meta[$key] ?? ''));
                if ($v !== '') {
                    $merged[$key] = $v;
                }
            }
            if (is_array($meta['usage'] ?? null)) {
                $haveUsage = true;
                $usageSum['input_tokens'] += (int) ($meta['usage']['input_tokens'] ?? $meta['usage']['prompt_tokens'] ?? 0);
                $usageSum['output_tokens'] += (int) ($meta['usage']['output_tokens'] ?? $meta['usage']['completion_tokens'] ?? 0);
            }
        } catch (Throwable $e) {
            error_log('[letters_ocr] finalize meta skipped: ' . $e->getMessage());
        }
    }

    $merged['note'] = implode('; ', $notes);
    $merged['usage'] = $haveUsage ? $usageSum : null;

    return $merged;
}

function letters_ocr_join_text(string $a, string $b): string
{
    $a = trim($a);
    $b = trim($b);
    if ($a === '') {
        return $b;
    }
    if ($b === '') {
        return $a;
    }

    return $a . "\n\n" . $b;
}

/**
 * @param list<array{path:string,mime:string,name?:string}> $images
 * @return array<string,mixed>
 */
function letters_ocr_request_vision(string $baseUrl, string $apiKey, string $model, string $prompt, array $images): array
{
    $content = [
        ['type' => 'text', 'text' => $prompt],
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

        return letters_ocr_post_messages($baseUrl, $apiKey, $model, $content);
    } finally {
        foreach ($tmpToClean as $p) {
            @unlink($p);
        }
    }
}

/**
 * @return array<string,mixed>
 */
function letters_ocr_request_text_meta(string $baseUrl, string $apiKey, string $model, string $bodyRu, string $bodyEn): array
{
    $text = letters_ocr_prompt_finalize_meta()
        . "\n\n--- body_ru ---\n"
        . mb_substr($bodyRu, 0, 12000)
        . "\n\n--- body_en ---\n"
        . mb_substr($bodyEn, 0, 12000);

    return letters_ocr_post_messages($baseUrl, $apiKey, $model, [
        ['type' => 'text', 'text' => $text],
    ]);
}

/**
 * @param list<array<string,mixed>> $content
 * @return array<string,mixed>
 */
function letters_ocr_post_messages(string $baseUrl, string $apiKey, string $model, array $content): array
{
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
