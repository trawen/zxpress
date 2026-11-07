<?php
/**
 * ManticoreSearch HTTP API client — replaces SphinxClient (sphinxapi.php).
 *
 * Provides search_query() and search_excerpts() that return data structures
 * compatible with the old SphinxClient format used in search.php.
 *
 * Host/port configurable via environment: MANTICORE_HOST, MANTICORE_PORT.
 */

define('MANTICORE_HOST', getenv('MANTICORE_HOST') ?: 'manticore');
define('MANTICORE_PORT', getenv('MANTICORE_PORT') ?: '9308');

// Sort mode constants (matching old SPH_SORT_* values)
define('SORT_RELEVANCE', 'relevance');
define('SORT_DATE_ASC',  'date_asc');
define('SORT_DATE_DESC', 'date_desc');

/**
 * Escape a string for ManticoreSearch SQL (single-quoted context).
 * Escapes both backslashes and single quotes to prevent injection.
 */
function manticore_escape(string $s): string
{
    return str_replace(["\\", "'"], ["\\\\", "\\'"], $s);
}

/**
 * Normalize query: fold ё→е, trim, collapse whitespace.
 * Multi-word Latin-only queries become a phrase ("The Royal Archer") for tighter ranking.
 * No Latin→Cyrillic transliteration.
 */
function normalize_query(string $q): string
{
    $q = str_replace(['ё', 'Ё'], ['е', 'Е'], $q);
    $q = preg_replace('/\s+/u', ' ', trim($q)) ?? trim($q);
    if ($q === '') {
        return '';
    }

    $latinWords = preg_match_all('/\b[a-zA-Z]{3,}\b/', $q) ?: 0;
    $hasCyrillic = (bool) preg_match('/\p{Cyrillic}/u', $q);
    if (!$hasCyrillic && $latinWords >= 2) {
        return '"' . str_replace('"', '', $q) . '"';
    }

    return $q;
}

/**
 * Send a request to ManticoreSearch HTTP API.
 *
 * @param string $endpoint  e.g. "/search", "/json/snippets"
 * @param array  $payload   JSON-encodable data
 * @return array|false      Decoded response or false on error
 */
function manticore_request(string $endpoint, array $payload)
{
    $url  = 'http://' . MANTICORE_HOST . ':' . MANTICORE_PORT . $endpoint;
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);

    if (getenv('LOG_LEVEL') === 'DEBUG') {
        error_log("[search_client] POST {$url} body_len=" . strlen($json));
    }

    $context = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\n",
            'content' => $json,
            'timeout' => 5,
        ],
    ]);

    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        error_log("[search_client] ERROR: ManticoreSearch request failed for {$endpoint}");
        return false;
    }

    if (getenv('LOG_LEVEL') === 'DEBUG') {
        error_log("[search_client] response_len=" . strlen($response));
    }

    return json_decode($response, true);
}

/**
 * Execute a search query against ManticoreSearch.
 *
 * Returns data in the same structure as SphinxClient::Query():
 *   ['total' => int, 'time' => float, 'matches' => [id => ['attrs' => [...]], ...]]
 *
 * @param string $query      Search query string
 * @param string $index      Index name (e.g. "test1", "test2")
 * @param int    $offset     Result offset
 * @param int    $limit      Max results to return
 * @param string $sort_mode  One of SORT_RELEVANCE, SORT_DATE_ASC, SORT_DATE_DESC
 * @return array             SphinxClient-compatible result array
 */
function search_query(string $query, string $index, int $offset = 0, int $limit = 10, string $sort_mode = SORT_RELEVANCE): array
{
    $empty = ['total' => 0, 'time' => 0, 'matches' => []];

    $query = normalize_query($query);
    if ($query === '') {
        return $empty;
    }

    $payload = [
        'index' => $index,
        'query' => [
            'query_string' => $query,
        ],
        'offset' => $offset,
        'limit'  => $limit,
    ];

    // Sort
    if ($sort_mode === SORT_DATE_ASC) {
        $payload['sort'] = [['date' => 'asc']];
    } elseif ($sort_mode === SORT_DATE_DESC) {
        $payload['sort'] = [['date' => 'desc']];
    }
    // SORT_RELEVANCE is the default — no sort needed

    $data = manticore_request('/search', $payload);

    if (!$data || !isset($data['hits'])) {
        error_log("[search_client] WARNING: empty or malformed response from /search");
        return $empty;
    }

    $result = [
        'total' => $data['hits']['total'] ?? 0,
        'time'  => ($data['took'] ?? 0) / 1000.0,
        'matches' => [],
    ];

    if (!empty($data['hits']['hits'])) {
        foreach ($data['hits']['hits'] as $hit) {
            $id    = $hit['_id'];
            $attrs = $hit['_source'] ?? [];
            $result['matches'][$id] = ['attrs' => $attrs];
        }
    }

    if (getenv('LOG_LEVEL') === 'DEBUG') {
        error_log("[search_client] search_query('{$query}', '{$index}'): total={$result['total']}, time={$result['time']}s");
    }

    return $result;
}

/**
 * Build search excerpts (snippets) via ManticoreSearch CALL SNIPPETS SQL API.
 *
 * Compatible with SphinxClient::BuildExcerpts().
 *
 * @param array  $docs   Array of document text strings
 * @param string $index  Index name
 * @param string $query  Search query for highlighting
 * @param array  $opts   Options: before_match, after_match, chunk_separator, limit_words, around
 * @return array|false   Array of highlighted snippets, or false on error
 */
function search_excerpts(array $docs, string $index, string $query, array $opts = [])
{
    if (empty($docs)) {
        return [];
    }

    $query = normalize_query($query);

    if (getenv('LOG_LEVEL') === 'DEBUG') {
        error_log("[search_client] search_excerpts: index={$index}, query={$query}, docs_count=" . count($docs));
    }

    // CALL SNIPPETS requires a plain index, not distributed
    $snippet_index = $index;
    if ($snippet_index === 'test1') {
        $snippet_index = 'test1_articles';
    }

    // Build SQL options string
    $sql_opts = [];
    if (isset($opts['before_match'])) {
        $sql_opts[] = "'" . manticore_escape($opts['before_match']) . "' AS before_match";
    }
    if (isset($opts['after_match'])) {
        $sql_opts[] = "'" . manticore_escape($opts['after_match']) . "' AS after_match";
    }
    if (isset($opts['chunk_separator'])) {
        $sql_opts[] = "'" . manticore_escape($opts['chunk_separator']) . "' AS chunk_separator";
    }
    if (isset($opts['limit_words'])) {
        $sql_opts[] = (int)$opts['limit_words'] . " AS limit_words";
    }
    if (isset($opts['around'])) {
        $sql_opts[] = (int)$opts['around'] . " AS around";
    }
    $opts_sql = !empty($sql_opts) ? ", " . implode(", ", $sql_opts) : "";

    $query_esc = manticore_escape($query);
    $index_esc = manticore_escape($snippet_index);

    // Batch all docs into a single CALL SNIPPETS (N+1 → 1 request)
    $doc_parts = [];
    foreach ($docs as $doc) {
        $doc_parts[] = "'" . manticore_escape((string)$doc) . "'";
    }
    $doc_list = implode(', ', $doc_parts);

    $sql = "CALL SNIPPETS(({$doc_list}), '{$index_esc}', '{$query_esc}'{$opts_sql})";

    $url = 'http://' . MANTICORE_HOST . ':' . MANTICORE_PORT . '/sql?mode=raw';
    $context = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => 'query=' . urlencode($sql),
            'timeout' => 10,
            'ignore_errors' => true,
        ],
    ]);

    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        error_log("[search_client] ERROR: batch CALL SNIPPETS failed for index={$index}");
        return array_map(function($d) { return strip_tags((string)$d); }, $docs);
    }

    $data = json_decode($response, true);

    if (empty($data[0]['data'])) {
        error_log("[search_client] WARN: batch CALL SNIPPETS returned no data for index={$index}");
        return array_map(function($d) { return strip_tags((string)$d); }, $docs);
    }

    $snippets = [];
    foreach ($data[0]['data'] as $i => $row) {
        $snippets[] = $row['snippet'] ?? strip_tags((string)($docs[$i] ?? ''));
    }

    // If ManticoreSearch returned fewer snippets than docs, fill remaining with plain text
    for ($i = count($snippets); $i < count($docs); $i++) {
        $snippets[] = strip_tags((string)$docs[$i]);
    }

    return $snippets;
}
