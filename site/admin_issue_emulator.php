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

function aiem_upload_error_message(int $code): string
{
	return match ($code) {
		UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'PNG-кадр слишком большой',
		UPLOAD_ERR_PARTIAL => 'PNG-кадр загружен частично',
		UPLOAD_ERR_NO_FILE => 'PNG-кадр не получен',
		UPLOAD_ERR_NO_TMP_DIR => 'Нет временной папки для загрузки (tmp переполнен?)',
		UPLOAD_ERR_CANT_WRITE => 'Не удалось записать кадр во временную папку (tmp переполнен?)',
		UPLOAD_ERR_EXTENSION => 'Загрузка PNG заблокирована расширением PHP',
		default => 'PNG-кадр не получен (код ' . $code . ')',
	};
}

function aiem_save_screenshot(mysqli $db, int $pressId, int $issueId, int $type, int $userId): array
{
	$file = $_FILES['screenshot'] ?? null;
	if (!is_array($file)) {
		return ['ok' => false, 'error' => 'PNG-кадр не получен'];
	}
	$uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
	if ($uploadError !== UPLOAD_ERR_OK) {
		error_log('[FIX] admin_issue_emulator upload error=' . $uploadError
			. ' files=' . json_encode(array_keys($_FILES))
			. ' post_keys=' . json_encode(array_keys($_POST)));
		return ['ok' => false, 'error' => aiem_upload_error_message($uploadError)];
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
	if (!empty($saved['ok'])) {
		screen_after_saved_webp($db, $screenId, $saved);
	}
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
	if (!empty($result['ok'])) {
		activity_batch_finalize($db);
	}
	aiem_json(!empty($result['ok']) ? 200 : 422, $result);
}

if ($pressId <= 0 || $issueId <= 0 || !aiem_issue_exists($db, $pressId, $issueId)) {
	header('HTTP/1.1 404 Not Found');
	echo 'Выпуск не найден';
	exit;
}

/**
 * Disk/tape images that can be picked from inside a ZIP.
 *
 * @return list<string>
 */
function aiem_zip_member_exts(): array
{
	return ['scl', 'trd', 'fdi', 'udi', 'mgt', 'img', 'sad', 'dsk', 'tap', 'tzx', 'z80', 'sna'];
}

/**
 * @return list<array{path:string,name:string,ext:string}>
 */
function aiem_zip_disk_members(string $zipPath): array
{
	if (!class_exists('ZipArchive') || !is_file($zipPath)) {
		return [];
	}
	$zip = new ZipArchive();
	if ($zip->open($zipPath) !== true) {
		return [];
	}
	$allowed = aiem_zip_member_exts();
	$out = [];
	for ($i = 0; $i < $zip->numFiles; $i++) {
		$stat = $zip->statIndex($i);
		if (!is_array($stat)) {
			continue;
		}
		$entry = str_replace('\\', '/', (string) ($stat['name'] ?? ''));
		if ($entry === '' || str_ends_with($entry, '/')) {
			continue;
		}
		$base = basename($entry);
		if ($base === '' || str_starts_with($base, '.')) {
			continue;
		}
		$ext = strtolower(pathinfo($base, PATHINFO_EXTENSION));
		if (!in_array($ext, $allowed, true)) {
			continue;
		}
		$out[] = [
			'path' => $entry,
			'name' => $base,
			'ext' => $ext,
		];
	}
	$zip->close();
	usort(
		$out,
		static fn (array $a, array $b): int => strnatcasecmp($a['name'], $b['name'])
	);

	return $out;
}

/**
 * Extract a ZIP member into /files/_aiem/ so USP/T80 get a URL ending with the real extension.
 *
 * @return array{leaf:string,url:string,fileName:string}|null
 */
function aiem_cache_zip_member(string $zipPath, int $fileId, string $memberPath, string $memberBase): ?array
{
	$safe = preg_replace('/[^A-Za-z0-9._-]+/', '_', $memberBase) ?: 'disk.bin';
	$leaf = '_aiem/' . $fileId . '_' . $safe;
	$outPath = zx_storage_path('files', $leaf);
	$dir = dirname($outPath);
	if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
		return null;
	}
	$zipMtime = (int) @filemtime($zipPath);
	if (is_file($outPath) && (int) @filemtime($outPath) >= $zipMtime && filesize($outPath) > 0) {
		return [
			'leaf' => $leaf,
			'url' => '/files/' . str_replace('%2F', '/', rawurlencode($leaf)),
			'fileName' => $memberBase,
		];
	}

	$zip = new ZipArchive();
	if ($zip->open($zipPath) !== true) {
		return null;
	}
	$stream = $zip->getStream($memberPath);
	if ($stream === false) {
		$zip->close();

		return null;
	}
	$tmp = $outPath . '.tmp';
	$dest = fopen($tmp, 'wb');
	if ($dest === false) {
		fclose($stream);
		$zip->close();

		return null;
	}
	stream_copy_to_stream($stream, $dest);
	fclose($stream);
	fclose($dest);
	$zip->close();
	if (!is_file($tmp) || filesize($tmp) <= 0 || !@rename($tmp, $outPath)) {
		@unlink($tmp);

		return null;
	}
	@touch($outPath, $zipMtime > 0 ? $zipMtime : time());

	return [
		'leaf' => $leaf,
		'url' => '/files/' . str_replace('%2F', '/', rawurlencode($leaf)),
		'fileName' => $memberBase,
	];
}

/**
 * @param list<string> $engines
 * @return array{id:int,name:string,fileName:string,ext:string,engines:list<string>,url:string}
 */
function aiem_disk_entry(int $id, string $display, string $fileName, string $ext, array $engines, string $url): array
{
	return [
		'id' => $id,
		'name' => $display,
		'fileName' => $fileName,
		'ext' => $ext,
		'engines' => $engines,
		'url' => $url,
	];
}

$uspExts = ['scl', 'trd', 'fdi', 'udi', 'zip', 'tap', 'tzx', 'z80', 'sna'];
$t80Exts = ['mgt', 'img', 'sad', 'zip', 'tap', 'tzx', 'z80', 'sna', 'scl', 'trd', 'dsk'];
$uspMemberExts = ['scl', 'trd', 'fdi', 'udi', 'tap', 'tzx', 'z80', 'sna'];
$t80MemberExts = ['mgt', 'img', 'sad', 'tap', 'tzx', 'z80', 'sna', 'scl', 'trd', 'dsk'];
$allowedExts = array_values(array_unique(array_merge($uspExts, $t80Exts)));
$disks = [];
$hasUsp = false;
$hasT80Prefer = false; // raw PlusD images — Turbo80 is the useful engine
$hasTrdos = false;
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
	$filePath = zx_storage_path('files', $name);
	if (!is_file($filePath)) {
		continue;
	}
	$fileId = (int) ($row['id'] ?? 0);
	$label = trim((string) ($row['file_title'] ?? ''));
	$display = $label !== '' ? $label . ' — ' . $name : $name;
	if ((int) ($row['id_issue'] ?? 0) === 0) {
		$display = 'издание — ' . $display;
	}

	if ($ext === 'zip') {
		$members = aiem_zip_disk_members($filePath);
		if ($members !== []) {
			$zipHasTrdos = false;
			$zipHasPlusd = false;
			foreach ($members as $member) {
				if (in_array($member['ext'], ['trd', 'scl', 'fdi', 'udi'], true)) {
					$zipHasTrdos = true;
				}
				if (in_array($member['ext'], ['mgt', 'img', 'sad'], true)) {
					$zipHasPlusd = true;
				}
			}
			if ($zipHasTrdos) {
				$hasTrdos = true;
			}
			if ($zipHasPlusd && !$zipHasTrdos) {
				$hasT80Prefer = true;
			} elseif (!$zipHasTrdos) {
				// ZIP without recognizable TR-DOS/PlusD images — keep old auto bias to Turbo80.
				$hasT80Prefer = true;
			}

			$many = count($members) > 1;
			$expanded = 0;
			foreach ($members as $memberIndex => $member) {
				$cached = aiem_cache_zip_member($filePath, $fileId, $member['path'], $member['name']);
				if ($cached === null) {
					continue;
				}
				$engines = [];
				if (in_array($member['ext'], $uspMemberExts, true)) {
					$engines[] = 'usp';
					$hasUsp = true;
				}
				if (in_array($member['ext'], $t80MemberExts, true)) {
					$engines[] = 't80';
				}
				if ($engines === []) {
					continue;
				}
				$memberDisplay = $many
					? ($member['name'] . ' · ' . $name)
					: $display;
				$disks[] = aiem_disk_entry(
					$fileId * 1000 + $memberIndex + 1,
					$memberDisplay,
					$cached['fileName'],
					$member['ext'],
					$engines,
					$cached['url']
				);
				$expanded++;
			}
			if ($expanded > 0) {
				// Expanded into members — do not also offer the raw ZIP.
				continue;
			}
			// Fall through to raw ZIP if extraction failed for every member.
		} else {
			$hasT80Prefer = true;
		}
	}

	$engines = [];
	if (in_array($ext, $uspExts, true)) {
		$engines[] = 'usp';
		$hasUsp = true;
	}
	if (in_array($ext, $t80Exts, true)) {
		$engines[] = 't80';
	}
	if (in_array($ext, ['mgt', 'img', 'sad'], true)) {
		$hasT80Prefer = true;
	}
	if (in_array($ext, ['trd', 'scl', 'fdi', 'udi'], true)) {
		$hasTrdos = true;
	}
	$disks[] = aiem_disk_entry(
		$fileId,
		$display,
		$name,
		$ext,
		$engines,
		'/files/' . rawurlencode($name)
	);
}

$engineReq = strtolower(trim((string) ($_GET['engine'] ?? 'usp')));
if (!in_array($engineReq, ['auto', 'usp', 't80'], true)) {
	$engineReq = 'usp';
}
if ($engineReq === 'auto') {
	// Prefer Turbo80 for PlusD/MGT (and ZIP magazines); USP for TR-DOS/tape dumps.
	$engine = ($hasT80Prefer && !$hasTrdos) ? 't80' : 'usp';
	if ($engine === 'usp' && !$hasUsp && $disks !== []) {
		$engine = 't80';
	}
} else {
	$engine = $engineReq;
}

$disksForEngine = [];
foreach ($disks as $disk) {
	if (in_array($engine, $disk['engines'], true)) {
		$disksForEngine[] = $disk;
	}
}

$config = [
	'pressId' => $pressId,
	'issueId' => $issueId,
	'engine' => $engine,
	'csrfToken' => csrf_token(),
	'disks' => $disksForEngine,
	't80Base' => '/t80/',
	'uploadUrl' => '/admin_issue_emulator.php?action=shot&id=' . $pressId . '&issue=' . $issueId,
];
$configJson = json_encode(
	$config,
	JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP
);
$isT80 = ($engine === 't80');
$scriptPath = $isT80
	? (__DIR__ . '/js/admin_issue_emulator_t80.js')
	: (__DIR__ . '/js/admin_issue_emulator.js');
$scriptVersion = is_readable($scriptPath) ? (string) filemtime($scriptPath) : '0';
$scriptSrc = $isT80
	? '/js/admin_issue_emulator_t80.js?v=' . $scriptVersion
	: '/js/admin_issue_emulator.js?v=' . $scriptVersion;
?>
<!doctype html>
<html lang="ru">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Эмулятор выпуска<?= $isT80 ? ' · Turbo80' : ' · USP' ?></title>
	<?php if (!$isT80): ?>
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
	<?php endif; ?>
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
		.aiem-stage--t80{width:320px;height:240px}
		#canvas{display:block;width:320px;height:240px;margin:-24px 0 0 -32px;image-rendering:pixelated;outline:none}
		#t80-mount{display:block;width:320px;height:240px;background:#000}
		#t80-mount > *{width:320px!important;height:240px!important;max-width:320px!important}
		#aiem-boot{position:absolute;inset:0;display:grid;place-items:center;background:rgba(244,238,224,.88);color:var(--smn-muted);z-index:2}
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
		.aiem-engine{font-size:11px;color:var(--smn-muted)}
	</style>
</head>
<body>
<?php if ($disksForEngine === []): ?>
	<div class="aiem-empty">
		<?php if ($disks === []): ?>
			У выпуска нет поддерживаемого файла (TRD/SCL/FDI/UDI/MGT/ZIP/TAP/TZX/Z80/SNA).
		<?php elseif ($isT80): ?>
			Для Turbo80 нет подходящего файла. <a href="?id=<?= (int) $pressId ?>&amp;issue=<?= (int) $issueId ?>&amp;engine=usp">Открыть USP</a>
		<?php else: ?>
			Для USP нет подходящего файла. <a href="?id=<?= (int) $pressId ?>&amp;issue=<?= (int) $issueId ?>&amp;engine=t80">Открыть Turbo80</a>
		<?php endif; ?>
	</div>
<?php else: ?>
	<div class="aiem-layout">
		<div class="aiem-stage<?= $isT80 ? ' aiem-stage--t80' : '' ?>">
			<?php if ($isT80): ?>
				<div id="t80-mount"></div>
			<?php else: ?>
				<canvas id="canvas" tabindex="0" width="320" height="240"></canvas>
			<?php endif; ?>
			<div id="aiem-boot"><?= $isT80 ? 'Загрузка Turbo80…' : 'Загрузка USP…' ?></div>
		</div>
		<div class="aiem-controls">
			<div class="aiem-engine">Движок: <strong><?= $isT80 ? 'Turbo80 (+D / MGT)' : 'USP (TR-DOS)' ?></strong></div>
			<label>Образ диска
				<select id="aiem-disk"></select>
			</label>
			<button type="button" class="primary" id="aiem-shot" disabled>Снять кадр</button>
			<button type="button" id="aiem-focus">Фокус в эмулятор</button>
			<div id="aiem-status">Загрузка эмулятора…</div>
			<div class="aiem-hint"><?= $isT80
				? 'PlusD/G+DOS: Space на заставке, буквы+Enter в меню поздних номеров.'
				: 'Кликните по экрану и управляйте как обычно.' ?></div>
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
	</div>
	<script id="aiem-config" type="application/json"><?= $configJson ?></script>
	<script src="<?= htmlspecialchars($scriptSrc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></script>
<?php endif; ?>
</body>
</html>
