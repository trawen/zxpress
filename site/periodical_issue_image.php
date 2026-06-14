<?php
require 'init.inc';
require_once __DIR__ . '/includes/periodical_issue_images.php';

$issueId = (int) ($_GET['issue_id'] ?? 0);
$width = (int) ($_GET['width'] ?? 0);

if ($issueId <= 0 || ($width !== 640 && $width !== 1280)) {
    header('HTTP/1.1 404 Not Found');
    exit;
}

if (!per_issue_has_webp_preview($issueId, $width)) {
    per_issue_ensure_webp_previews($issueId);
}

$path = per_issue_cover_webp_path($issueId, $width);
if (!is_file($path) || filesize($path) <= 0) {
    header('HTTP/1.1 404 Not Found');
    exit;
}

header('Content-Type: image/webp');
header('Content-Length: ' . (string) filesize($path));
header('Cache-Control: public, max-age=31536000, immutable');
readfile($path);
exit;
