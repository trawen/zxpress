<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/site/includes/storage_paths.php';

final class StoragePathsTest extends TestCase
{
	private string $tmpRoot;

	protected function setUp(): void
	{
		parent::setUp();
		$this->tmpRoot = sys_get_temp_dir() . '/zxpress-storage-test-' . uniqid('', true);
		putenv('ZXPRESS_DATA_ROOT=' . $this->tmpRoot);
	}

	protected function tearDown(): void
	{
		putenv('ZXPRESS_DATA_ROOT');
		$this->removeTree($this->tmpRoot);
		parent::tearDown();
	}

	public function testZxStorageWriteCreatesNestedDirectoriesAndWritesContent(): void
	{
		$ok = zx_storage_write('articles', 'nested/test-article.txt', 'hello');
		self::assertTrue($ok);

		$path = zx_storage_path('articles', 'nested/test-article.txt');
		self::assertFileExists($path);
		self::assertSame('hello', (string) file_get_contents($path));
	}

	public function testZxStorageCopyUploadedFileCreatesDirectoriesAndCopies(): void
	{
		$tmp = tempnam(sys_get_temp_dir(), 'zxpress-upload-');
		self::assertNotFalse($tmp);
		file_put_contents($tmp, 'png-bytes');

		$ok = zx_storage_copy_uploaded_file('screens', '1/test.png', (string) $tmp);
		self::assertTrue($ok);

		$path = zx_storage_path('screens', '1/test.png');
		self::assertFileExists($path);
		self::assertSame('png-bytes', (string) file_get_contents($path));

		@unlink((string) $tmp);
	}

	private function removeTree(string $path): void
	{
		if (!is_dir($path)) {
			return;
		}

		$items = scandir($path);
		if ($items === false) {
			return;
		}

		foreach ($items as $item) {
			if ($item === '.' || $item === '..') {
				continue;
			}
			$full = $path . '/' . $item;
			if (is_dir($full)) {
				$this->removeTree($full);
			} else {
				@unlink($full);
			}
		}
		@rmdir($path);
	}
}
