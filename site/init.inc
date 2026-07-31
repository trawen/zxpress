<?php
/**
 * Bot detection: skip session_start() for crawlers to prevent
 * millions of orphan session files. Sessions are only used for admin login.
 */
function is_bot() {
	$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
	if ($ua === '') {
		return true;
	}
	return (bool) preg_match(
		'/bot|crawl|spider|slurp|googlebot|bingbot|yandex|baidu|gptbot|claudebot'
		. '|bytespider|chatgpt|ahrefsbot|semrushbot|mj12bot|curl|wget'
		. '|python-requests|go-http-client|petalbot|dotbot|rogerbot/i',
		$ua
	);
}

if (is_bot()) {
	$_SESSION = [];
} else {
	ini_set('session.cookie_httponly', 1);
	$zxpress_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
		|| (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
	ini_set('session.cookie_secure', $zxpress_https ? 1 : 0);
	ini_set('session.cookie_samesite', 'Lax');
	ini_set('session.use_strict_mode', 1);
	session_start();
}

// [FIX] After nginx real_ip for Traefik: log when X-Forwarded-For is present but REMOTE_ADDR is not a trusted proxy.
if (PHP_SAPI !== 'cli' && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
	$ra = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
	$trusted_raw = getenv('ZXPRESS_TRUSTED_PROXY_IPS');
	$trusted_list = [];
	if ($trusted_raw !== false && $trusted_raw !== '') {
		foreach (explode(',', $trusted_raw) as $one) {
			$one = trim($one);
			if ($one !== '') {
				$trusted_list[] = $one;
			}
		}
	}
	$is_trusted_proxy = $ra !== '' && in_array($ra, $trusted_list, true);
	if ($ra !== '' && !$is_trusted_proxy) {
		static $zxpress_realip_diag = 0;
		if ($zxpress_realip_diag < 8) {
			error_log('[FIX] client_ip REMOTE_ADDR=' . $ra . ' X-Forwarded-For=' . (string) $_SERVER['HTTP_X_FORWARDED_FOR']);
			$zxpress_realip_diag++;
		}
	}
}

require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/storage_paths.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/locale.php';
require_once __DIR__ . '/includes/sape_integration.php';
require_once __DIR__ . '/includes/activity.php';

// DEBUG: always show all PHP errors on screen
//error_reporting(E_ALL);
//ini_set('display_errors', '1');
//ini_set('display_startup_errors', '1');
//ini_set('html_errors', '1');

set_error_handler(function ($severity, $message, $file, $line) {
	if (!(error_reporting() & $severity)) {
		return true;
	}
	error_log("PHP Error [$severity]: $message in $file:$line");
	// Let PHP also output the error.
	return false;
});

set_exception_handler(function ($e) {
	error_log("Uncaught Exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
	http_response_code(500);
	echo "<pre>";
	echo "Uncaught " . get_class($e) . ": " . $e->getMessage() . "\n";
	echo "in " . $e->getFile() . ":" . $e->getLine() . "\n\n";
	echo $e->getTraceAsString();
	echo "</pre>";
});

if (isset($_REQUEST['id']) && $_REQUEST['id'] != "0") {
	$_REQUEST['id'] = intval($_REQUEST['id']);
}

$article_breadcrumbs = array();

define('DB_HOST', getenv('DB_HOST') ?: 'db');
define('DB_USER', getenv('DB_USER') ?: 'zxpress_u');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'zxpress_db');

if (DB_PASS === '' && getenv('ALLOW_EMPTY_DB_PASSWORD') !== '1') {
	error_log('DB_PASS is empty — set DB_PASS in environment or ALLOW_EMPTY_DB_PASSWORD=1 for local dev only');
	http_response_code(503);
	die('Service temporarily unavailable');
}

$months = array(
	"01" => "января",
	"02" => "февраля",
	"03" => "марта",
	"04" => "апреля",
	"05" => "мая",
	"06" => "июня",
	"07" => "июля",
	"08" => "августа",
	"09" => "сентября",
	"10" => "октября",
	"11" => "ноября",
	"12" => "декабря",
);

$mnt = array(
	"01" => "январь",
	"02" => "февраль",
	"03" => "март",
	"04" => "апрель",
	"05" => "май",
	"06" => "июнь",
	"07" => "июль",
	"08" => "август",
	"09" => "сентябрь",
	"10" => "октябрь",
	"11" => "ноябрь",
	"12" => "декабрь",
);

$db = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$db) {
	error_log('DB connection failed: ' . mysqli_connect_error());
	http_response_code(503);
	die('Service temporarily unavailable');
}
mysqli_set_charset($db, 'utf8mb4');

define('SMARTY_DIR', __DIR__ . '/smarty/libs/');
require_once(SMARTY_DIR . 'Smarty.class.php');
$smarty = new Smarty;
$smarty->caching = false;
$smarty->compile_check = true;

$smarty->template_dir = __DIR__ . '/smarty/zxpress/templates/';
$smarty->compile_dir = zx_storage_dir('smarty_templates_c') . '/';
$smarty->config_dir = __DIR__ . '/smarty/zxpress/configs/';
$smarty->cache_dir = zx_storage_dir('smarty_cache') . '/';

// Auto-escape HTML in {$vars}; use |nofilter for trusted HTML (Sape, Manticore excerpts, etc.)
$smarty->escape_html = true;

$smarty->assign('host', "/");

handle_login($db, $smarty);

// Auto-batch admin POST mutations into activity_batch (legacy log trigger attaches too).
activity_admin_request_boot($db);

$url = explode("?", $_SERVER['REQUEST_URI']);
$smarty->assign('url', $url[0]);

setup_sape($smarty, $url);

setup_locale($smarty, $db, $months);

$smarty->registerPlugin("function", "friendly_url", "friendly_url");

require_once __DIR__ . '/includes/snailmail_new_assets.php';
$smarty->registerPlugin('function', 'smn_styles', 'smarty_function_smn_styles');

?>
