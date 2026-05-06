<?php
/**
 * Smoke tests for search_client.php functions.
 * Run: php tests/test_search_client.php
 */

require_once __DIR__ . '/../site/includes/search_client.php';

$passed = 0;
$failed = 0;

function test($name, $condition) {
    global $passed, $failed;
    if ($condition) {
        $passed++;
    } else {
        $failed++;
        echo "FAIL: {$name}\n";
    }
}

// --- manticore_escape ---

test('escape: single quote',
    manticore_escape("test'") === "test\\'");

test('escape: backslash before quote (injection vector)',
    manticore_escape("test\\'") === "test\\\\\\'");

test('escape: plain string unchanged',
    manticore_escape("normal text") === "normal text");

test('escape: backslash only',
    manticore_escape("back\\slash") === "back\\\\slash");

test('escape: multiple quotes',
    manticore_escape("it's a 'test'") === "it\\'s a \\'test\\'");

test('escape: empty string',
    manticore_escape("") === "");

// --- normalize_query: ё→е folding ---

test('normalize: ё→е lowercase',
    normalize_query("ёж") === "еж");

test('normalize: Ё→Е uppercase',
    normalize_query("Ёж") === "Еж");

test('normalize: mixed ёЁ',
    normalize_query("Ёлка ёлка") === "Елка елка");

// --- normalize_query: transliteration OR expansion ---

test('normalize: english 3+ chars gets OR expansion with original preserved',
    strpos(normalize_query("BASIC"), "BASIC") !== false);

test('normalize: english 3+ chars gets OR expansion with transliteration',
    strpos(normalize_query("BASIC"), "БАСИК") !== false);

test('normalize: OR expansion uses parentheses and pipe',
    preg_match('/\(BASIC \| БАСИК\)/', normalize_query("BASIC")) === 1);

test('normalize: spectrum transliteration',
    strpos(normalize_query("spectrum"), "спектрум") !== false);

test('normalize: spectrum preserves original',
    strpos(normalize_query("spectrum"), "spectrum") !== false);

// --- normalize_query: short words NOT transliterated ---

test('normalize: 2-char latin word not transliterated',
    normalize_query("ZX") === "ZX");

test('normalize: 1-char latin word not transliterated',
    normalize_query("A test") === "A (test | тест)");

// --- normalize_query: cyrillic passthrough ---

test('normalize: cyrillic unchanged',
    normalize_query("спектрум") === "спектрум");

test('normalize: mixed cyrillic and latin',
    strpos(normalize_query("ZX spectrum"), "spectrum") !== false
    && strpos(normalize_query("ZX spectrum"), "спектрум") !== false
    && strpos(normalize_query("ZX spectrum"), "ZX") !== false);

// --- normalize_query: whitespace ---

test('normalize: collapse whitespace',
    normalize_query("  hello   world  ") === "(hello | хелло) (world | ворлд)");

test('normalize: empty string',
    normalize_query("") === "");

// --- transliterate_word ---

test('translit: digraph sh',
    transliterate_word("sha") === "ша");

test('translit: digraph ch',
    transliterate_word("cha") === "ча");

test('translit: digraph zh',
    transliterate_word("zha") === "жа");

test('translit: full word kino',
    transliterate_word("kino") === "кино");

test('translit: uppercase',
    transliterate_word("BASIC") === "БАСИК");

// --- Results ---

$total = $passed + $failed;
echo "\n{$passed}/{$total} tests passed";
if ($failed > 0) {
    echo ", {$failed} FAILED";
}
echo "\n";
exit($failed > 0 ? 1 : 0);
