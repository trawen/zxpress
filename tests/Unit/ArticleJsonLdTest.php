<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once ZXPRESS_SITE_ROOT . '/includes/article_jsonld.php';

final class ArticleJsonLdTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		date_default_timezone_set('UTC');
	}

	public function testIso8601Utc(): void
	{
		self::assertSame('2024-01-05T00:00:00+00:00', article_jsonld_iso8601(1704412800));
		self::assertSame('', article_jsonld_iso8601(0));
	}

	public function testAbsoluteUrl(): void
	{
		self::assertSame(
			'https://zxpress.ru/screens/1/10.webp',
			article_jsonld_absolute_url('https://zxpress.ru', '/screens/1/10.webp')
		);
		self::assertSame(
			'https://cdn.example/a.jpg',
			article_jsonld_absolute_url('https://zxpress.ru', 'https://cdn.example/a.jpg')
		);
	}

	public function testNewsArticlePayloadShape(): void
	{
		$payload = article_newsarticle_jsonld(
			'https://zxpress.ru',
			'https://zxpress.ru/ru/ezines/demo/1/hello',
			[
				'title' => 'Заголовок статьи',
				'title_eng' => 'Article headline',
				'date' => 1704412800,
				'dt' => 894571200,
			],
			['id' => 42, 'format' => 'webp'],
			[
				[
					'id' => 7,
					'nickname' => 'coder',
					'name_ru' => 'Иван',
					'name_en' => 'Ivan',
					'slug_ru' => 'ivan',
					'slug_en' => 'ivan',
				],
			],
			['title' => 'Spectrofon', 'title_plain' => 'Spectrofon'],
			'/ru/ezines/spectrofon',
			894571200,
			false,
			'Короткое описание'
		);

		self::assertSame('https://schema.org', $payload['@context']);
		self::assertSame('NewsArticle', $payload['@type']);
		self::assertSame('Заголовок статьи', $payload['headline']);
		self::assertSame(['https://zxpress.ru/screens/1/42.webp'], $payload['image']);
		self::assertSame(gmdate('c', 894571200), $payload['datePublished']);
		self::assertSame(gmdate('c', 1704412800), $payload['dateModified']);
		self::assertSame('Person', $payload['author'][0]['@type']);
		self::assertSame('Иван', $payload['author'][0]['name']);
		self::assertStringContainsString('/ru/authors/ivan', $payload['author'][0]['url']);
		self::assertSame('ZXPRESS', $payload['publisher']['name']);
		self::assertSame('Короткое описание', $payload['description']);
		self::assertSame('Spectrofon', $payload['isPartOf']['isPartOf']['name']);
	}

	public function testFallbackAuthorIsPressOrganization(): void
	{
		$payload = article_newsarticle_jsonld(
			'https://zxpress.ru',
			'https://zxpress.ru/ru/ezines/demo/1/hello',
			['title' => 'Hello', 'title_eng' => '', 'date' => 0, 'dt' => 0],
			null,
			[],
			['title_plain' => 'Demo Mag'],
			'/ru/ezines/demo',
			1000000000,
			false,
			''
		);

		self::assertSame('Organization', $payload['author'][0]['@type']);
		self::assertSame('Demo Mag', $payload['author'][0]['name']);
		self::assertArrayNotHasKey('image', $payload);
		self::assertSame(gmdate('c', 1000000000), $payload['datePublished']);
	}

	public function testEncodeEscapesScriptBreakouts(): void
	{
		$json = article_newsarticle_jsonld_encode([
			'@context' => 'https://schema.org',
			'@type' => 'NewsArticle',
			'headline' => '</script><script>alert(1)',
		]);
		self::assertStringNotContainsString('</script>', $json);
		self::assertStringContainsString('\u003C/script\u003E', $json);
	}
}
