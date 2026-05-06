<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap — load production code needed by unit tests (no init.inc / DB).
 * Application code lives under site/; tests live under tests/ at repo root.
 */

if (!defined('ZXPRESS_SITE_ROOT')) {
	define('ZXPRESS_SITE_ROOT', dirname(__DIR__) . '/site');
}

if (!isset($_SERVER['SMARTY_PHPUNIT_DISABLE_HEADERS'])) {
	$_SERVER['SMARTY_PHPUNIT_DISABLE_HEADERS'] = '1';
}

require_once __DIR__ . '/bootstrap_session.php';
require_once __DIR__ . '/Integration/IntegrationTestCase.php';
require_once ZXPRESS_SITE_ROOT . '/includes/search_client.php';
require_once ZXPRESS_SITE_ROOT . '/includes/functions.php';
require_once ZXPRESS_SITE_ROOT . '/includes/csrf.php';
