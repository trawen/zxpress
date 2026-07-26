<?php

require 'init.inc'; // MARKER_12345
require_once __DIR__ . '/includes/ezine_slugs.php';
require_once __DIR__ . '/includes/books_public.php';
require_once __DIR__ . '/includes/authors_slugs.php';

function search_ui_is_new(): bool
{
	return defined('SEARCH_UI_VARIANT') && SEARCH_UI_VARIANT === 'new';
}

function search_catalog_url(bool $isEng): string
{
	if (search_ui_is_new()) {
		return ezn_path_prefix($isEng) . '/search-new';
	}
	return '/search.php' . ($isEng ? '?lng=eng' : '');
}

function search_page_url(bool $isEng, string $q, int $page, string $sort, $from): string
{
	$base = search_catalog_url($isEng);
	$params = [];
	if ($q !== '') {
		$params['q'] = $q;
	}
	if ($page > 0) {
		$params['p'] = $page;
	}
	if ($sort !== '') {
		$params['s'] = $sort;
	}
	if ($from !== '' && $from !== null && (string) $from !== '0') {
		$params['f'] = $from;
	}
	if (!search_ui_is_new() && $isEng && !isset($params['lng'])) {
		$params['lng'] = 'eng';
	}
	if ($params === []) {
		return $base;
	}
	$sep = (strpos($base, '?') === false) ? '?' : '&';
	return $base . $sep . http_build_query($params);
}

$limit = 10;

//error_reporting(E_ALL);


mb_internal_encoding("UTF-8");

require_once("includes/search_client.php");

$j = $_GET['jump'] ?? '';
$q = mb_substr($_GET['q'] ?? '', 0, 64);
$p = intval($_GET['p'] ?? 0);
$s = $_GET['s'] ?? 'rw';
$f = $_GET['f'] ?? '';
$id = intval($_GET['id'] ?? 0);
$t = time();

$search = [];
$docs = [];
$inf = [];

/**
 * Log search query to MySQL (analytics) — prepared statement.
 * Column `type` is INT in schema.
 */
$log_search_row = function (mysqli $db, string $text, int $date, int $type, int $article_id) {
  $stmt = $db->prepare("INSERT INTO search (`text`,`date`,`type`,`article_id`) VALUES (?,?,?,?)");
  if (!$stmt) {
    error_log('search.php: INSERT prepare failed: ' . $db->error);
    return;
  }
  $stmt->bind_param("siii", $text, $date, $type, $article_id);
  $stmt->execute();
};

if ($p == 0 and $q) {
  $log_search_row($db, $q, $t, 128, 0);
}

$isEngPage = ($smarty->getTemplateVars('lng') === 'eng');

if ($j == "ezine" and $q) {

  $log_search_row($db, $q, $t, 0, $id);
  $articleUrl = ezn_canonical_article_url($db, $id, $isEngPage);
  header('Location: ' . ($articleUrl ?? '/article.php?id=' . $id));
  exit;

}
elseif ($j == "book" and $q) {

  $log_search_row($db, $q, $t, 1, $id);
  header('Location: ' . books_url_chapter($id, $isEngPage));
  exit;

}
elseif ($j == "zxnet" and $q) {

  $log_search_row($db, $q, $t, 2, $id);
  header("Location: /zxnet/.php?id=$id");
  exit;

}

$sort_mode = get_sort_mode($s);

$smarty->assign('query', $q);
$smarty->assign('q', $q);
$smarty->assign('page', $p);
$smarty->assign('sort', $s);
$smarty->assign('from', $f);

$hitOpen = search_ui_is_new() ? '<mark class="smn-search-hit">' : "<b class='find'>";
$hitClose = search_ui_is_new() ? '</mark>' : '</b>';

if (getenv('LOG_LEVEL') === 'DEBUG') {
  error_log('[FIX] search.php: layout — zxpress.ru parity (350/280 toolbar, 210px sidebar, colgroup table) style.css + search.tpl');
}

if ($f) {

  $result = search_query($q, "test2", $p * $limit, $limit, $sort_mode);
  count_pages($result);

  $n = 0;
  if ( ! empty($result["matches"]) ) {

    foreach ( $result["matches"] as $id => $info ) {
                  
        $echo_id = (int)$id;
        $z = db_select($db, "SELECT *, echos_titles2.title AS name FROM echos_zxnet,echos_titles2,echos_subjs2 WHERE echos_zxnet.id=? AND echos_titles2.id=echos_zxnet.echo_id AND echos_subjs2.id=echos_zxnet.subj_id LIMIT 1", "i", $echo_id);
        $t = $z ? mysqli_fetch_array($z) : false;
        $inf[$n] = $t ?: [];
        if ($t) {
          $inf[$n]['name'] = title_plain((string) ($t['name'] ?? ''));
          if (isset($t['title'])) {
            $inf[$n]['title'] = title_plain((string) $t['title']);
          }
          $inf[$n]['date'] = $t['date'] ? date("d ".$months[date("m", $t['date'])]." Y", $t['date'] ) : "";
        } else {
          $inf[$n]['date'] = '';
        }
        $plainZx = strip_tags((string) ($t['text'] ?? ''));
        $docs[] = plain_text_decode_entities($plainZx);

        $n++;
     }
                  
  }

  if ($result['total'] && !empty($docs)) {

      $index = "test2";
      $opts = array
      (
        "before_match"    => $hitOpen,
        "after_match"   => $hitClose,
        "chunk_separator" => " ... ",
        "limit_words"       => 35,
        "around"      => 35,
      );

      $res = search_excerpts($docs, $index, $q, $opts);

      if ( !$res ) {

        error_log("[FIX] search.php: excerpts request failed for index={$index}");

      } else {

        $n = 0;
        foreach ( $res as $entry )
        {

            $entry = first_fix(first_fix(first_fix((string)$entry)));
            $t = $inf[$n] ?? [];
            $t['text'] = $entry;
            $search[] = $t;

            $n++;
        }

      }
  }

}
else {

  $result = search_query($q, "test1", $p * $limit, $limit, $sort_mode);

  count_pages($result);

  $n = 0;
  if ( ! empty($result["matches"]) ) {

     foreach ( $result["matches"] as $id1 => $info ) {

        $id = $id1;
        
        $inf[$n]['id'] = $id;
        $inf[$n]['title'] = title_plain(cutString((string) $result["matches"][$id]['attrs']['title'], 64));

        if (mb_substr($inf[$n]['title'], -1, 1) == ".") {

        //  $inf[$n]['title'] = mb_substr($inf[$n]['title'], 0, mb_strlen($inf[$n]['title'])-1);

        }

        $inf[$n]['name'] = title_plain((string) $result["matches"][$id]['attrs']['name']);
        $inf[$n]['id1'] = intval($result["matches"][$id]['attrs']['id1']);
        $inf[$n]['id2'] = intval($result["matches"][$id]['attrs']['id2']);
        $inf[$n]['id3'] = intval($result["matches"][$id]['attrs']['id3']);
        $d = $result["matches"][$id]['attrs']['date'];
        $inf[$n]['date'] = $d ? date("d ".$months[date("m", $d)]." Y", $d ) : "";
        $img = explode("/", (string) ($result["matches"][$id]['attrs']['img'] ?? ''));
        $inf[$n]['type'] = intval($result["matches"][$id]['attrs']['type']);
        $inf[$n]['img'] = '';
        $inf[$n]['img_format'] = 'png';

        if ($inf[$n]['type'] === 1) {
          // Manticore img is "id_press/article_id" — not a screen id. Resolve cover by issue.
          $pressId = $inf[$n]['id3'];
          $issueId = $inf[$n]['id2'];
          $screen = null;
          if ($pressId > 0 && $issueId > 0) {
            $stmtScr = $db->prepare('SELECT id, format FROM screens WHERE id_press=? AND id_issue=? ORDER BY type ASC, id ASC LIMIT 1');
            if ($stmtScr) {
              $stmtScr->bind_param('ii', $pressId, $issueId);
              $stmtScr->execute();
              $screen = $stmtScr->get_result()->fetch_assoc();
              $stmtScr->close();
            }
          }
          if (!$screen && $pressId > 0) {
            $stmtScr = $db->prepare('SELECT id, format FROM screens WHERE id_press=? ORDER BY type ASC, id ASC LIMIT 1');
            if ($stmtScr) {
              $stmtScr->bind_param('i', $pressId);
              $stmtScr->execute();
              $screen = $stmtScr->get_result()->fetch_assoc();
              $stmtScr->close();
            }
          }
          if ($screen) {
            $inf[$n]['img'] = (string) (int) ($screen['id'] ?? 0);
            $fmt = strtolower(preg_replace('/[^a-z0-9]+/i', '', (string) ($screen['format'] ?? 'png')) ?: 'png');
            $inf[$n]['img_format'] = $fmt !== '' ? $fmt : 'png';
          }
          $articleUrl = ezn_canonical_article_url($db, $inf[$n]['id1'], $isEngPage);
          $inf[$n]['article_url'] = $articleUrl ?? '/article.php?id=' . $inf[$n]['id1'];
          $issueUrl = ezn_canonical_issue_url_by_ids($db, $inf[$n]['id3'], $inf[$n]['id2'], $isEngPage);
          $inf[$n]['issue_url'] = $issueUrl ?? '/issue.php?id=' . $inf[$n]['id3'] . '#' . rawurlencode((string) $inf[$n]['id2']);
        } else {
          // Books: img attr is "image_id/book_id".
          $bookImg = (string) ($img[0] ?? '');
          if ($bookImg !== '' && $bookImg !== '0') {
            $inf[$n]['img'] = $bookImg;
          }
          $inf[$n]['chapter_url'] = books_url_chapter($inf[$n]['id1'], $isEngPage);
          $inf[$n]['book_url'] = books_url_book($inf[$n]['id2'], $isEngPage);
        }

        $docs[] = search_source_doc_from_match($result["matches"][$id]['attrs'], $inf[$n]['type']);

        $n++;
     }
                  
  }

  if ($result['total'] && !empty($docs)) {

      $index = "test1";
      $opts = array
      (
        "before_match"    => $hitOpen,
        "after_match"   => $hitClose,
        "chunk_separator" => " ... ",
        "limit_words"       => 25,
        "around"      => 25,
      );

      $res = search_excerpts($docs, $index, $q, $opts);

      if ( !$res ) {

        error_log("[FIX] search.php: excerpts request failed for index={$index}");

      } else {

        $n = 0;

        foreach ( $res as $entry )
        {

            $entry = first_fix(first_fix(first_fix((string)$entry)));

            $t = $inf[$n] ?? [];
            $t['text'] = $entry;

            $search[] = $t;

            $n++;
        }

      }
  }

}

$smarty->assign('search', $search);

if (search_ui_is_new()) {
  $catalog = search_catalog_url($isEngPage);
  $smarty->assign('search_catalog_url', $catalog);
  $smarty->assign('ezines_catalog_url', ezn_url_catalog_new($isEngPage));
  $smarty->assign('letters_catalog_url', ezn_path_prefix($isEngPage) . '/snailmail-new');
  $smarty->assign('authors_catalog_url', authors_url_catalog($isEngPage));
  $smarty->assign('smn_nav_authors_active', false);
  $smarty->assign('smn_nav_ezines_active', false);
  $smarty->assign('smn_nav_gallery_active', false);
  $smarty->assign('smn_nav_zxnet_active', false);
  $smarty->assign('smn_nav_guestbook_active', false);
  $smarty->assign('smn_nav_updates_active', false);
  $smarty->assign('smn_nav_map_active', false);
  $smarty->assign('url_rus', htmlspecialchars(search_page_url(false, $q, $p, $s, $f), ENT_QUOTES, 'UTF-8'));
  $smarty->assign('url_eng', htmlspecialchars(search_page_url(true, $q, $p, $s, $f), ENT_QUOTES, 'UTF-8'));
  if ($q !== '') {
    $smarty->assign('title', ($isEngPage ? 'Search: ' : 'Поиск: ') . $q);
    $smarty->assign('description', $isEngPage
      ? ('Search results for “' . $q . '” on ZXPRESS')
      : ('Результаты поиска «' . $q . '» на ZXPRESS'));
  } else {
    $smarty->assign('title', $isEngPage ? 'Search' : 'Поиск');
    $smarty->assign('description', $isEngPage
      ? 'Search ZX Spectrum magazines, newspapers and books'
      : 'Поиск по журналам, газетам и книгам для ZX Spectrum');
  }
  if ($smarty->getTemplateVars('found') === null && $q === '') {
    $smarty->assign('found', 0);
    $smarty->assign('time', '0');
  }
  $smarty->display('search_new.tpl');
} else {
  include "right.php";
  $smarty->display('search.tpl');
}

function count_pages($result) {

  Global $smarty, $limit;

  $smarty->assign('found', $result['total']);
  $smarty->assign('time', $result['time']);

  if ($result['total'] > $limit) {

    $nm_pages = ceil($result['total'] / $limit);

    for ($n=0; $n < $nm_pages; $n++) {
    
      $t['num'] = $n;
      $t['show'] = $n+1;
      $pg[]=$t;

    }
    $smarty->assign('pages', $pg);
    
  }

}

function first_fix($entry) {

    $entry = trim((string)($entry ?? ''));
    if (mb_strlen($entry) < 3) {
        return $entry;
    }
    $padded = " " . $entry;
    $b = mb_strpos($entry, "<b");
    if ($b === false) {
      $b = mb_strpos($entry, "<mark");
    }
    $f1 = mb_strlen($padded) > 4 ? mb_strpos($padded, ". ", 4) : false;
    $f2 = mb_strpos($padded, "!", 0);
    $f3 = mb_strpos($padded, "?", 0);
    $f4 = mb_strpos($padded, ",", 0);
    $f5 = mb_strpos($padded, "-", 0);
    $f6 = mb_strpos($padded, ":", 0);
    $f7 = mb_strpos($padded, ";", 0);

    if ($f2 and $f2 < $f1) {$f1 = $f2;}
    if ($f3 and $f3 < $f1) {$f1 = $f3;}
    if ($f4 and $f4 < $f1) {$f1 = $f4;}
    if ($f5 and $f5 < $f1) {$f1 = $f5;}
    if ($f6 and $f6 < $f1) {$f1 = $f6;}
    if ($f7 and $f7 < $f1) {$f1 = $f7;}

    if ($f1 and $f1 < $b) {

        $entry = mb_substr($entry, $f1+1);
        
    }
    elseif ($b == 5 or mb_substr($entry, 0,4) == "... ") {

        $entry = mb_substr($entry, 4);

    }

    return trim($entry);

}

function get_sort_mode($s) {

  if ($s == "dw") {
    return SORT_DATE_ASC;
  }
  elseif ($s == "up") {
    return SORT_DATE_DESC;
  }

  return SORT_RELEVANCE;

}

function cutString($string, $maxlen) {
    $string = (string)($string ?? '');
    $len = (mb_strlen($string) > $maxlen)
        ? mb_strripos(mb_substr($string, 0, $maxlen), ' ')
        : $maxlen
    ;
    $cutStr = mb_substr($string, 0, $len);

    return (mb_strlen($string) > $maxlen)
        ? $cutStr . '...'
        : $cutStr
    ;
}

function search_source_doc_from_match(array $attrs, int $type): string {
    $id1 = isset($attrs['id1']) ? (int) $attrs['id1'] : 0;
    if ($id1 <= 0) {
        return '';
    }

    $key = ($type === 1) ? 'articles' : 'chapters';
    $path = zx_storage_path($key, (string) $id1);
    if (!is_readable($path)) {
        error_log('[search] WARN missing source doc key=' . $key . ' id=' . $id1 . ' path=' . $path);
        return '';
    }

    $raw = (string) file_get_contents($path);
    if ($raw === '') {
        return '';
    }

    return plain_text_decode_entities(strip_tags($raw));
}


?>
