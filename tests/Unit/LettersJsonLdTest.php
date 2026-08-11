<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once ZXPRESS_SITE_ROOT . '/includes/letters_jsonld.php';

final class LettersJsonLdTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		date_default_timezone_set('UTC');
	}

	public function testMessagePayload(): void
	{
		$letter = [
			'title_display' => 'Письмо из Саратова',
			'date' => '1996-05-01',
			'published_at' => '2024-01-10 12:00:00',
			'created_at' => '2024-01-09 12:00:00',
			'from_author_name' => 'coder^group',
			'from_author_url' => '/ru/authors/coder',
			'to_author_name' => 'dizzy',
			'to_author_url' => '/ru/authors/dizzy',
		];
		$payload = letters_message_jsonld(
			'https://zxpress.ru',
			'https://zxpress.ru/ru/snailmail/letter-slug',
			$letter,
			[['preview_url' => '/letters/preview/9.jpg', 'display_src' => '/letters/preview/9.jpg']],
			false,
			'Краткое описание'
		);

		self::assertSame('Message', $payload['@type']);
		self::assertSame('Письмо из Саратова', $payload['headline']);
		self::assertSame(['https://zxpress.ru/letters/preview/9.jpg'], $payload['image']);
		self::assertSame('Person', $payload['sender']['@type']);
		self::assertSame('coder^group', $payload['sender']['name']);
		self::assertSame('dizzy', $payload['recipient']['name']);
		self::assertSame(gmdate('c', strtotime('1996-05-01')), $payload['dateSent']);
		self::assertSame(gmdate('c', strtotime('2024-01-10 12:00:00')), $payload['datePublished']);
		self::assertSame('CollectionPage', $payload['isPartOf']['@type']);
	}

	public function testCatalogPayload(): void
	{
		$payload = letters_catalog_jsonld(
			'https://zxpress.ru',
			'https://zxpress.ru/ru/snailmail',
			false,
			'Каталог',
			[
				['title_display' => 'A', 'public_url' => '/ru/snailmail/a'],
				['title_display' => 'B', 'public_url' => '/ru/snailmail/b'],
			]
		);
		self::assertSame('CollectionPage', $payload['@type']);
		self::assertSame('ItemList', $payload['mainEntity']['@type']);
		self::assertCount(2, $payload['mainEntity']['itemListElement']);
		self::assertSame(1, $payload['mainEntity']['itemListElement'][0]['position']);
	}
}
