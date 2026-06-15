<?php

$submit = $_POST['submit'] ?? '';
// Do not regenerate captcha on POST: the user submits the code from the previous GET;
// generate_captcha() overwrites $_SESSION['captcha_code'] and makes validation always fail.
if ($submit !== 'отправить' && $submit !== 'submit') {
	$captcha_token = generate_captcha();
} else {
	$captcha_token = $_SESSION['captcha_token'] ?? '';
	if ($captcha_token === '') {
		$captcha_token = generate_captcha();
	}
}
$smarty->assign('captcha_token', $captcha_token);

$smarty->assign(
	'e2e_captcha_plain',
	getenv('E2E_EXPOSE_CAPTCHA') === '1' ? ($_SESSION['captcha_code'] ?? '') : ''
);

$confirm_code = strtolower($_POST['confirm_code'] ?? '');
$message = plain_text_normalize_for_storage(strip_tags($_POST['message'] ?? ''));
$user_name = plain_text_normalize_for_storage(strip_tags($_POST['user_name'] ?? ''));
$user_email = trim(strip_tags($_POST['user_email'] ?? ''));
$article_id = intval($_REQUEST['id'] * 1);

$ip = $_SERVER["REMOTE_ADDR"] ?? '';

$captcha_expected = $_SESSION['captcha_code'] ?? '';




function tst_err($a) {
global $message, $user_name, $user_email, $user_site, $code, $confirm_code, $c, $author_id, $captcha_expected;
$err=0;
$isEng = (($_GET['lng'] ?? $_POST['lng'] ?? '') === 'eng');
if (!$message) {
	$err++;
	$ert = $isEng
		? "$err. The <b>message</b> field is too short.<br>"
		: "$err. Поле <b>сообщение</b> слишком короткое.<br>";
}
if (mb_strlen($message, "UTF-8")>1024) {
	$err++;
	$ert .= $isEng
		? "$err. The <b>message</b> field is too long.<br>"
		: "$err. Поле <b>сообщение</b> слишком длинное.<br>";
}
if (!$user_name) {
	$err++;
	$ert .= $isEng
		? "$err. <b>Name</b> is too short.<br>"
		: "$err. <b>Имя</b> слишком короткое.<br>";
}
if ($user_email and mb_strlen($user_email)<7) {
	$err++;
	$ert .= $isEng
		? "$err. The <b>email</b> field is too short.<br>"
		: "$err. Поле <b>почта</b> слишком короткое.<br>";
}
if ($captcha_expected === '' || $confirm_code !== $captcha_expected) {
	$err++;
	$ert .= $isEng
		? "$err. <b>Wrong code.</b><br>"
		: "$err. <b>Неверный код.</b><br>";
}


return $ert;
}


$tm = time(); 
$ert = tst_err(0);


$stmt = mysqli_prepare($db, "SELECT * FROM comments WHERE id_article=? ORDER BY date DESC");
mysqli_stmt_bind_param($stmt, "i", $article_id);
mysqli_stmt_execute($stmt);
$z = mysqli_stmt_get_result($stmt);

$n = 0;
$isEng = (($_GET['lng'] ?? $_POST['lng'] ?? '') === 'eng');
while ($t = mysqli_fetch_array($z)) {

$t['number'] = $n+1;

$t['date'] = $isEng ? date("j F Y", $t['date']) : date("j.m.Y", $t['date']);
$comments[$n] = $t;

$n++;
}
$smarty->assign('comments', $comments);


if ($submit=="отправить" or $submit=="submit") {
csrf_verify();

if (!$ert) {
$stmt = mysqli_prepare($db, "INSERT INTO comments (`id`, `text`, `nickname`, `email`, `ip`, `date`, `id_article`) VALUES (NULL, ?, ?, ?, ?, ?, ?)");
mysqli_stmt_bind_param($stmt, "ssssii", $message, $user_name, $user_email, $ip, $tm, $article_id);
mysqli_stmt_execute($stmt);

$redirect = $_SERVER['REQUEST_URI'];
if (strpos($redirect, 'lng=eng') === false && (($_POST['lng'] ?? '') === 'eng')) {
	$redirect .= (strpos($redirect, '?') !== false ? '&' : '?') . 'lng=eng';
}
header("Location: " . $redirect);
exit;
}
else {

$smarty->assign('error', $ert);
$smarty->assign('message', $message);
$smarty->assign('user_name', $user_name);
$smarty->assign('user_email', $user_email);

}


}




?>