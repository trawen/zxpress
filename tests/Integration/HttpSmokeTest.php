<?php

declare(strict_types=1);

/**
 * HTTP smoke: public pages return 200 and no PHP fatal strings in body.
 */
final class HttpSmokeTest extends IntegrationTestCase
{
	private static function baseUrl(): string
	{
		$u = getenv('HTTP_SMOKE_BASE_URL');
		if ($u !== false && $u !== '') {
			return rtrim($u, '/');
		}
		return 'http://127.0.0.1:80';
	}

	/**
	 * @return array{0:int,1:string,2:float}
	 */
	private static function httpGet(string $url): array
	{
		$t0 = microtime(true);
		$ctx = stream_context_create([
			'http' => [
				'timeout' => 15,
				'ignore_errors' => true,
			],
		]);
		// get_headers() reliably returns the status line in CLI; http_get_last_response_headers()
		// after file_get_contents() is not always populated the same way across PHP versions.
		$code = 0;
		$hdrLines = @get_headers($url, false, $ctx);
		if ($hdrLines !== false && isset($hdrLines[0]) && preg_match('#HTTP/[\d.]+\s+(\d{3})#', $hdrLines[0], $m)) {
			$code = (int)$m[1];
		}
		$body = @file_get_contents($url, false, $ctx);
		$ms = (microtime(true) - $t0) * 1000.0;
		return [$code, $body === false ? '' : $body, $ms];
	}

	public static function setUpBeforeClass(): void
	{
		self::requireIntegrationEnv();
	}

	/**
	 * @return iterable<string, array{0:string}>
	 */
	public static function urlProvider(): iterable
	{
		$paths = ['/', '/ezines.php', '/news.php', '/search.php'];
		foreach ($paths as $p) {
			yield $p => [$p];
		}
	}

	/**
	 * @dataProvider urlProvider
	 */
	public function testPublicPageSmoke(string $path): void
	{
		$base = self::baseUrl();
		$url = $base . $path;
		[$code, $body, $ms] = self::httpGet($url);
		error_log(sprintf('[http_smoke] url=%s code=%d time_ms=%.1f', $url, $code, $ms));
		self::assertSame(200, $code, 'non-200 for ' . $url . ' body_prefix=' . substr($body, 0, 500));
		$lower = strtolower($body);
		self::assertStringNotContainsString('fatal error', $lower);
		self::assertStringNotContainsString('parse error', $lower);
	}
}
