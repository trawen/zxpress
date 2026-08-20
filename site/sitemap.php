<?php
/**
 * Dynamic sitemap.xml — Russian pretty URLs of the new public site.
 * Served as /sitemap.xml via nginx rewrite.
 */
require 'init.inc';
require_once __DIR__ . '/includes/sitemap_builder.php';

header('Content-Type: application/xml; charset=UTF-8');
header('Cache-Control: public, max-age=3600');

$origin = zxpress_canonical_origin();
sitemap_render($db, $origin);
