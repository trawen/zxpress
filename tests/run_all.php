<?php
/**
 * Unified CLI test runner: runs every tests/.../test_*.php recursively.
 *
 * Usage:
 *   php tests/run_all.php
 *   php tests/run_all.php security    # only tests/security/test_*.php
 *   LOG_LEVEL=debug php tests/run_all.php
 *
 * Exit code: 0 if all scripts exit 0, else 1.
 */

declare(strict_types=1);

$testsRoot = __DIR__;
$phpBinary = (defined('PHP_BINARY') && PHP_BINARY !== '') ? PHP_BINARY : 'php';

/** @var array<int, string> */
$argvRest = array_slice($argv, 1);
$subFilter = '';
foreach ($argvRest as $arg) {
	if ($arg === '-h' || $arg === '--help') {
		fwrite(STDOUT, "Usage: php run_all.php [subdir]\n");
		fwrite(STDOUT, "  subdir   optional path under tests/ (e.g. security)\n");
		fwrite(STDOUT, "Env: LOG_LEVEL=debug|info|warn|error — runner meta to stderr (default: info)\n");
		exit(0);
	}
	if ($arg !== '' && $arg[0] !== '-') {
		$subFilter = $arg;
		break;
	}
}

$logLevel = getenv('LOG_LEVEL');
if ($logLevel === false || $logLevel === '') {
	$logLevel = 'info';
}
$logLevel = strtolower($logLevel);
$levels = ['debug' => 0, 'info' => 1, 'warn' => 2, 'error' => 3];
$currentLevel = $levels[$logLevel] ?? 1;

$log = static function (string $level, string $message) use ($levels, $currentLevel): void {
	$lv = $levels[$level] ?? 1;
	if ($lv < $currentLevel) {
		return;
	}
	$ts = date('c');
	fwrite(STDERR, "[run_all] {$ts} [{$level}] {$message}\n");
};

$log('info', 'start');
$log('info', 'php_version=' . PHP_VERSION);
$log('info', 'php_binary=' . $phpBinary);
$log('info', 'cwd=' . getcwd());
$log('info', 'tests_root=' . $testsRoot);
if ($subFilter !== '') {
	$log('info', 'filter_subdir=' . $subFilter);
}

/**
 * @return list<string> absolute paths
 */
function find_test_files(string $root, string $subFilter): array {
	$out = [];
	$dir = new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS);
	$it = new RecursiveIteratorIterator($dir);
	/** @var SplFileInfo $file */
	foreach ($it as $file) {
		if (!$file->isFile()) {
			continue;
		}
		$name = $file->getFilename();
		if (!preg_match('/^test_.*\\.php$/', $name)) {
			continue;
		}
		$path = $file->getPathname();
		$rel = substr($path, strlen($root) + 1);
		$rel = str_replace('\\', '/', $rel);
		if ($subFilter !== '') {
			$norm = trim(str_replace('\\', '/', $subFilter), '/');
			$prefix = $norm . '/';
			if (strpos($rel, $prefix) !== 0 && $rel !== $norm) {
				continue;
			}
		}
		$out[] = $path;
	}
	sort($out, SORT_STRING);
	return $out;
}

$files = find_test_files($testsRoot, $subFilter);
$log('info', 'discovered_files=' . count($files));

if (count($files) === 0) {
	$log('error', 'no test_*.php files matched');
	exit(1);
}

$failedRel = [];
$ok = 0;
foreach ($files as $abs) {
	$rel = substr($abs, strlen($testsRoot) + 1);
	$rel = str_replace('\\', '/', $rel);
	$log('debug', 'exec file=' . $rel);
	fwrite(STDOUT, "\n" . str_repeat('=', 60) . "\n");
	fwrite(STDOUT, "RUN: {$rel}\n");
	fwrite(STDOUT, str_repeat('=', 60) . "\n");
	$cmd = escapeshellarg($phpBinary) . ' ' . escapeshellarg($abs);
	$exitCode = 0;
	passthru($cmd, $exitCode);
	if ($exitCode !== 0) {
		$failedRel[] = $rel;
		$log('warn', 'file_failed rel=' . $rel . ' exit=' . $exitCode);
	} else {
		$ok++;
	}
}

$failCount = count($failedRel);
$total = count($files);
fwrite(STDOUT, "\n" . str_repeat('=', 60) . "\n");
fwrite(STDOUT, "run_all summary: {$ok}/{$total} files OK");
if ($failCount > 0) {
	fwrite(STDOUT, ", {$failCount} FAILED");
}
fwrite(STDOUT, "\n");
if ($failCount > 0) {
	fwrite(STDOUT, "Failed files:\n");
	foreach ($failedRel as $r) {
		fwrite(STDOUT, "  - {$r}\n");
	}
	$log('info', 'done status=fail failed=' . $failCount);
	exit(1);
} else {
	$log('info', 'done status=ok');
	exit(0);
}
