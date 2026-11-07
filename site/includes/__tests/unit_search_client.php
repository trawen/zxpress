<?php
declare(strict_types=1);

$LOG_LEVEL = strtoupper(getenv('LOG_LEVEL') ?: 'STANDARD');
$INCLUDES_DIR = dirname(__DIR__); // .../site/includes
$SEARCH_CLIENT_PATH = $INCLUDES_DIR . '/search_client.php';

require_once $SEARCH_CLIENT_PATH;

function fail(string $msg): void
{
    echo "[unit_search_client] ERROR FAIL {$msg}\n";
    exit(1);
}

function assertEq($actual, $expected, string $msg): void
{
    if ($actual !== $expected) {
        $a = is_string($actual) ? $actual : var_export($actual, true);
        $e = is_string($expected) ? $expected : var_export($expected, true);
        fail("{$msg}. expected=" . $e . " actual=" . $a);
    }
}

// Escaping for Manticore SQL single-quoted context
$input = "a'b\\c"; // a ' b \ c
$expected = "a\\'b\\\\c"; // a\'b\\c (string contents)
assertEq(manticore_escape($input), $expected, 'manticore_escape');

// normalize_query: ё -> е, whitespace, no translit
assertEq(normalize_query("ёлка"), 'елка', 'normalize_query(ё->е)');
assertEq(normalize_query('spectrum'), 'spectrum', 'normalize_query single latin unchanged');
assertEq(normalize_query('BASIC spectrum ZX'), 'BASIC spectrum ZX', 'normalize_query mixed latin unchanged');
assertEq(
    normalize_query("  hello   world "),
    '"hello world"',
    'normalize_query multi-word latin → phrase'
);
assertEq(
    normalize_query('The Royal Archer'),
    '"The Royal Archer"',
    'normalize_query English phrase'
);

echo "[unit_search_client] INFO PASS unit_search_client\n";
exit(0);
