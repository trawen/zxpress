<?php
/**
 * Embedded Unreal Speccy Portable console for issue screenshots.
 */
require 'init.inc';
require_once __DIR__ . '/includes/admin_helpers.php';
require_once __DIR__ . '/includes/screen_images.php';

if (empty($_SESSION['login'])) {
	header('HTTP/1.1 403 Forbidden');
	exit;
}

function aiem_json(int $status, array $payload): never
{
	http_response_code($status);
	header('Content-Type: application/json; charset=utf-8');
	header('Cache-Control: no-store');
	echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	exit;
}

function aiem_issue_exists(mysqli $db, int $pressId, int $issueId): bool
{
	$z = db_select(
		$db,
		'SELECT id FROM issue WHERE id=? AND id_press=? LIMIT 1',
		'ii',
		$issueId,
		$pressId
	);

	return (bool) ($z && $z->fetch_assoc());
}

function aiem_save_screenshot(mysqli $db, int $pressId, int $issueId, int $type, int $userId): array
{
	$file = $_FILES['screenshot'] ?? null;
	if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
		return ['ok' => false, 'error' => 'PNG-кадр не получен'];
	}
	$tmp = (string) ($file['tmp_name'] ?? '');
	if ($tmp === '' || !is_uploaded_file($tmp)) {
		return ['ok' => false, 'error' => 'Некорректный файл кадра'];
	}
	if ((int) ($file['size'] ?? 0) <= 0 || (int) ($file['size'] ?? 0) > 2 * 1024 * 1024) {
		return ['ok' => false, 'error' => 'Некорректный размер кадра'];
	}

	$info = @getimagesize($tmp);
	if (!$info || (string) ($info['mime'] ?? '') !== 'image/png') {
		return ['ok' => false, 'error' => 'Кадр должен быть PNG'];
	}
	if ((int) ($info[0] ?? 0) !== 256 || (int) ($info[1] ?? 0) !== 192) {
		return ['ok' => false, 'error' => 'Ожидался кадр 256×192'];
	}

	$created = time();
	$ok = db_exec(
		$db,
		'INSERT INTO screens (id_press, id_issue, type, date, format) VALUES (?,?,?,?,?)',
		'iiiis',
		$pressId,
		$issueId,
		$type,
		$created,
		'webp'
	);
	if (!$ok) {
		return ['ok' => false, 'error' => 'Не удалось создать запись скриншота'];
	}

	$screenId = (int) mysqli_insert_id($db);
	$saved = screen_save_upload_as_webp($tmp, $screenId);
	if (empty($saved['ok'])) {
		db_exec($db, 'DELETE FROM screens WHERE id=? LIMIT 1', 'i', $screenId);
		screen_delete_files($screenId);

		return ['ok' => false, 'error' => (string) ($saved['error'] ?? 'Не удалось сохранить изображение')];
	}

	admin_log($db, $pressId, $userId, $created, 2, 0, $issueId, $screenId);

	return [
		'ok' => true,
		'id' => $screenId,
		'url' => screen_public_url($screenId),
	];
}

$pressId = (int) ($_REQUEST['id'] ?? 0);
$issueId = (int) ($_REQUEST['issue'] ?? 0);

if (($_GET['action'] ?? '') === 'shot') {
	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
		aiem_json(405, ['ok' => false, 'error' => 'POST required']);
	}
	csrf_verify();
	if ($pressId <= 0 || $issueId <= 0 || !aiem_issue_exists($db, $pressId, $issueId)) {
		aiem_json(404, ['ok' => false, 'error' => 'Выпуск не найден']);
	}
	$type = (int) ($_POST['type'] ?? 0);
	if (!in_array($type, [0, 1, 2], true)) {
		$type = 0;
	}
	$result = aiem_save_screenshot(
		$db,
		$pressId,
		$issueId,
		$type,
		(int) ($_SESSION['id_username'] ?? 0)
	);
	aiem_json(!empty($result['ok']) ? 200 : 422, $result);
}

if ($pressId <= 0 || $issueId <= 0 || !aiem_issue_exists($db, $pressId, $issueId)) {
	header('HTTP/1.1 404 Not Found');
	echo 'Выпуск не найден';
	exit;
}

$allowedExts = ['scl', 'trd', 'fdi', 'udi', 'zip', 'tap', 'tzx', 'z80', 'sna'];
$disks = [];
$z = db_select(
	$db,
	'SELECT id, name, file_title, id_issue FROM files'
		. ' WHERE `delete`=0 AND (id_issue=? OR (id_press=? AND id_issue=0))'
		. ' ORDER BY (id_issue = 0) ASC, id ASC',
	'ii',
	$issueId,
	$pressId
);
while ($z && ($row = $z->fetch_assoc())) {
	$name = basename((string) ($row['name'] ?? ''));
	$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
	if ($name === '' || !in_array($ext, $allowedExts, true)) {
		continue;
	}
	if (!is_file(zx_storage_path('files', $name))) {
		continue;
	}
	$label = trim((string) ($row['file_title'] ?? ''));
	$display = $label !== '' ? $label . ' — ' . $name : $name;
	if ((int) ($row['id_issue'] ?? 0) === 0) {
		$display = 'издание — ' . $display;
	}
	$disks[] = [
		'id' => (int) $row['id'],
		'name' => $display,
		'url' => '/files/' . rawurlencode($name),
	];
}

$config = [
	'pressId' => $pressId,
	'issueId' => $issueId,
	'csrfToken' => csrf_token(),
	'disks' => $disks,
	'uploadUrl' => '/admin_issue_emulator.php?action=shot&id=' . $pressId . '&issue=' . $issueId,
];
$configJson = json_encode(
	$config,
	JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP
);
// Nginx serves /js with `expires max, immutable`, so the URL has to change.
$scriptPath = __DIR__ . '/js/admin_issue_emulator.js';
$scriptVersion = is_readable($scriptPath) ? (string) filemtime($scriptPath) : '0';
?>
<!doctype html>
<html lang="ru">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Эмулятор выпуска</title>
	<script>
	(function () {
		try {
			Object.defineProperty(window, 'devicePixelRatio', {
				configurable: true,
				get: function () { return 1; }
			});
		} catch (_) {}
	})();
	</script>
	<style>
		:root{
			--smn-ink:rgba(10,9,8,.8);
			--smn-muted:rgb(60,55,50);
			--smn-paper:rgb(244,238,224);
			--smn-line:rgb(201,184,150);
			--smn-surface:#fff;
			--smn-accent:rgb(164,30,0);
		}
		*{box-sizing:border-box}html,body{margin:0;background:var(--smn-paper);color:var(--smn-ink);font:13px Verdana,sans-serif}
		body{padding:10px}.aiem-layout{display:flex;gap:14px;align-items:flex-start;flex-wrap:wrap}
		.aiem-stage{width:256px;height:192px;overflow:hidden;background:#000;border:1px solid var(--smn-line);position:relative}
		#canvas{display:block;width:320px;height:240px;margin:-24px 0 0 -32px;image-rendering:pixelated;outline:none}
		#aiem-boot{position:absolute;inset:0;display:grid;place-items:center;background:rgba(244,238,224,.88);color:var(--smn-muted)}
		.aiem-controls{min-width:245px;max-width:340px;display:grid;gap:9px}
		label{display:grid;gap:4px;color:var(--smn-muted)}select,button{font:inherit;padding:7px;background:var(--smn-surface);color:var(--smn-ink);border:1px solid var(--smn-line)}
		button{cursor:pointer;font-weight:bold}button:disabled{opacity:.45;cursor:default}.primary{font-weight:bold}
		#aiem-status{min-height:34px;color:var(--smn-muted)}.ok{color:#2a6a2a!important}.err{color:var(--smn-accent)!important}.busy{color:#8a6a00!important}
		.aiem-hint{font-size:11px;line-height:1.4;color:var(--smn-muted)}
		.aiem-empty{padding:24px;border:1px solid var(--smn-line);color:var(--smn-muted)}
		.aiem-queue{margin-top:14px;border-top:1px solid var(--smn-line);padding-top:10px;display:grid;gap:9px}
		.aiem-queue-head{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
		.aiem-queue-head span{color:var(--smn-muted)}
		.aiem-shots{display:flex;gap:10px;flex-wrap:wrap}
		.aiem-shot{margin:0;padding:6px;display:grid;gap:5px;justify-items:stretch;background:var(--smn-surface);border:1px solid var(--smn-line)}
		.aiem-shot img{display:block;width:128px;height:96px;image-rendering:pixelated;background:#000}
		.aiem-shot button{padding:4px;font-size:11px}
		.aiem-shot-del{font-weight:normal;color:var(--smn-accent)}
	</style>
</head>
<body>
<?php if ($disks === []): ?>
	<div class="aiem-empty">У выпуска нет поддерживаемого файла (TRD/SCL/FDI/UDI/ZIP/TAP/TZX/Z80/SNA).</div>
<?php else: ?>
	<div class="aiem-layout">
		<div class="aiem-stage">
			<canvas id="canvas" tabindex="0" width="320" height="240"></canvas>
			<div id="aiem-boot">Загрузка USP…</div>
		</div>
		<div class="aiem-controls">
			<label>Файл выпуска
				<select id="aiem-disk"></select>
			</label>
			<button type="button" class="primary" id="aiem-shot" disabled>Снять кадр</button>
			<button type="button" id="aiem-focus">Фокус в эмулятор</button>
			<div id="aiem-status">Загрузка эмулятора…</div>
			<div class="aiem-hint">Кликните по экрану и управляйте как обычно. Экран показан 1:1 без бордюра — кадр сохраняется ровно таким, 256×192.</div>
		</div>
	</div>
	<div class="aiem-queue">
		<div class="aiem-queue-head">
			<strong>Очередь кадров</strong>
			<span id="aiem-count">Очередь пуста</span>
			<button type="button" class="primary" id="aiem-upload" disabled>Отправить на сервер</button>
			<button type="button" id="aiem-clear" disabled>Очистить очередь</button>
		</div>
		<div class="aiem-shots" id="aiem-shots"></div>
		<div class="aiem-hint">Кадры хранятся только в браузере. На сервер уходят и конвертируются в WebP лишь те, что останутся в очереди после удаления лишних. Тип скриншота потом задаётся в списке экранов.</div>
	</div>
	<script id="aiem-config" type="application/json"><?= $configJson ?></script>
	<script src="/js/admin_issue_emulator.js?v=<?= $scriptVersion ?>"></script>
<?php endif; ?>
</body>
</html>
