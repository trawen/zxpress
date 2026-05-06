<?php
session_start();

$token = $_GET['token'] ?? '';
if ($token === '' || !isset($_SESSION['captcha_token']) || $token !== $_SESSION['captcha_token'] || !isset($_SESSION['captcha_code'])) {
    header("HTTP/1.1 403 Forbidden");
    exit;
}

$code = $_SESSION['captcha_code'];
$im1 = imagecreate(150, 20);

$background_color = imagecolorallocate($im1, 255, 255, 255);
imagecolortransparent($im1, $background_color);

$x = 90;
for ($i = 0; $i < 6; $i++) {
    $text_color = imagecolorallocate($im1, rand(0, 160), rand(0, 160), rand(0, 160));
    imagestring($im1, 20, $x, -3 + rand(0, 6), $code[$i], $text_color);
    $x += 9;
}

header("Content-type: image/png");
imagepng($im1);
imagedestroy($im1);
?>
