<?php
/**
 * Legacy /ezines-old/... entry — always serves the current (new) article UI.
 * Public URLs should prefer /{lang}/ezines/...
 */
define('EZINES_UI_VARIANT', 'new');
define('EZINES_SECTION', 'ezines');

require __DIR__ . '/article.php';
