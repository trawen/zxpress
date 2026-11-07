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

// --- normalize_query: no Latin→Cyrillic transliteration ---

test('normalize: single latin word unchanged',
    normalize_query("BASIC") === "BASIC");

test('normalize: spectrum unchanged',
    normalize_query("spectrum") === "spectrum");

test('normalize: short latin unchanged',
    normalize_query("ZX") === "ZX");

test('normalize: multi-word latin becomes phrase',
    normalize_query("A test") === '"A test"');

test('normalize: cyrillic unchanged',
    normalize_query("спектрум") === "спектрум");

test('normalize: mixed cyrillic and latin kept as-is',
    normalize_query("ZX спектрум") === "ZX спектрум");

// --- normalize_query: whitespace ---

test('normalize: collapse whitespace + latin phrase',
    normalize_query("  hello   world  ") === '"hello world"');

test('normalize: empty string',
    normalize_query("") === "");

// --- Results ---

$total = $passed + $failed;
echo "\n{$passed}/{$total} tests passed";
if ($failed > 0) {
    echo ", {$failed} FAILED";
}
echo "\n";
exit($failed > 0 ? 1 : 0);
