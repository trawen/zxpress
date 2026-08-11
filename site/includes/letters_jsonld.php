<?php

declare(strict_types=1);

/**
 * Schema.org JSON-LD for the snailmail / letters section.
 */

require_once __DIR__ . '/article_jsonld.php';
require_once __DIR__ . '/authors_slugs.php';
require_once __DIR__ . '/letters_slugs.php';

/**
 * Parse DB date/datetime into unix timestamp (0 if empty/invalid).
 *
 * @param mixed $date
 */
function letters_jsonld_ts($date): int
{
	if ($date === null || $date === '') {
		return 0;
	}
	if (is_int($date) || (is_string($date) && ctype_digit($date))) {
		$n = (int) $date;
		return $n > 0 ? $n : 0;
	}
	$ts = strtotime((string) $date);

	return ($ts !== false && $ts > 0) ? $ts : 0;
}

/**
 * @return array{name:string,url:string}|null
 */
function letters_jsonld_person_from_enriched(array $letter, string $prefix, string $origin): ?array
{
	$name = title_plain((string) ($letter[$prefix . '_author_name'] ?? ''));
	if ($name === '') {
		return null;
	}
	$person = [
		'@type' => 'Person',
		'name' => $name,
	];
	$url = trim((string) ($letter[$prefix . '_author_url'] ?? ''));
	if ($url !== '') {
		$person['url'] = article_jsonld_absolute_url($origin, $url);
	}

	return $person;
}

/**
 * Build schema.org Message payload for a single letter page.
 *
 * @param array<string,mixed> $letter enriched letter row
 * @param list<array<string,mixed>> $images letter_images rows with preview_url / display_src / original_url
 */
function letters_message_jsonld(
	string $origin,
	string $canonicalUrl,
	array $letter,
	array $images,
	bool $isEng,
	string $description = ''
): array {
	$headline = title_plain((string) ($letter['title_display'] ?? ''));
	if ($headline === '') {
		$headline = $isEng ? 'Letter' : 'Письмо';
	}

	$sentTs = letters_jsonld_ts($letter['date'] ?? null);
	$publishedTs = letters_jsonld_ts($letter['published_at'] ?? null);
	if ($publishedTs <= 0) {
		$publishedTs = letters_jsonld_ts($letter['created_at'] ?? null);
	}
	if ($publishedTs <= 0) {
		$publishedTs = $sentTs;
	}
	$modifiedTs = $publishedTs;
	foreach ([$sentTs, letters_jsonld_ts($letter['updated_at'] ?? null)] as $candidate) {
		if ($candidate > $modifiedTs) {
			$modifiedTs = $candidate;
		}
	}

	$imageUrls = [];
	foreach ($images as $img) {
		$rel = (string) ($img['display_src'] ?? $img['preview_url'] ?? $img['original_url'] ?? '');
		$abs = article_jsonld_absolute_url($origin, $rel);
		if ($abs !== '' && !in_array($abs, $imageUrls, true)) {
			$imageUrls[] = $abs;
		}
	}

	$sender = letters_jsonld_person_from_enriched($letter, 'from', $origin);
	$recipient = letters_jsonld_person_from_enriched($letter, 'to', $origin);

	$catalogUrl = article_jsonld_absolute_url(
		$origin,
		letters_url_catalog($isEng)
	);

	$payload = [
		'@context' => 'https://schema.org',
		'@type' => 'Message',
		'name' => $headline,
		'headline' => $headline,
		'mainEntityOfPage' => [
			'@type' => 'WebPage',
			'@id' => $canonicalUrl,
		],
		'publisher' => [
			'@type' => 'Organization',
			'name' => 'ZXPRESS',
			'url' => article_jsonld_absolute_url($origin, '/'),
		],
		'isPartOf' => [
			'@type' => 'CollectionPage',
			'name' => $isEng ? 'Snailmail letters' : 'Бумажные письма',
			'url' => $catalogUrl,
		],
	];

	$description = title_plain($description);
	if ($description !== '') {
		$payload['description'] = $description;
	}

	if ($imageUrls !== []) {
		$payload['image'] = $imageUrls;
	}

	$publishedIso = article_jsonld_iso8601($publishedTs);
	if ($publishedIso !== '') {
		$payload['datePublished'] = $publishedIso;
	}
	$modifiedIso = article_jsonld_iso8601($modifiedTs);
	if ($modifiedIso !== '') {
		$payload['dateModified'] = $modifiedIso;
	}
	$sentIso = article_jsonld_iso8601($sentTs);
	if ($sentIso !== '') {
		$payload['dateSent'] = $sentIso;
	}

	if ($sender !== null) {
		$payload['sender'] = $sender;
		$payload['author'] = [$sender];
	}
	if ($recipient !== null) {
		$payload['recipient'] = $recipient;
	}

	return $payload;
}

/**
 * CollectionPage JSON-LD for the letters catalog.
 *
 * @param list<array<string,mixed>> $letterRows enriched list rows (optional ItemList)
 */
function letters_catalog_jsonld(
	string $origin,
	string $canonicalUrl,
	bool $isEng,
	string $description = '',
	array $letterRows = []
): array {
	$name = $isEng ? 'Snailmail letters' : 'Бумажные письма';
	$payload = [
		'@context' => 'https://schema.org',
		'@type' => 'CollectionPage',
		'name' => $name,
		'url' => $canonicalUrl,
		'mainEntityOfPage' => [
			'@type' => 'WebPage',
			'@id' => $canonicalUrl,
		],
		'isPartOf' => [
			'@type' => 'WebSite',
			'name' => 'ZXPRESS',
			'url' => article_jsonld_absolute_url($origin, '/'),
		],
		'publisher' => [
			'@type' => 'Organization',
			'name' => 'ZXPRESS',
			'url' => article_jsonld_absolute_url($origin, '/'),
		],
	];

	$description = title_plain($description);
	if ($description !== '') {
		$payload['description'] = $description;
	}

	$ogImage = article_jsonld_absolute_url($origin, '/img/snailmail.png');
	if ($ogImage !== '') {
		$payload['image'] = [$ogImage];
	}

	$items = [];
	$pos = 1;
	foreach ($letterRows as $row) {
		$url = trim((string) ($row['public_url'] ?? ''));
		if ($url === '') {
			continue;
		}
		$title = title_plain((string) ($row['title_display'] ?? ''));
		$entry = [
			'@type' => 'ListItem',
			'position' => $pos,
			'url' => article_jsonld_absolute_url($origin, $url),
		];
		if ($title !== '') {
			$entry['name'] = $title;
		}
		$items[] = $entry;
		$pos++;
		if ($pos > 20) {
			break;
		}
	}
	if ($items !== []) {
		$payload['mainEntity'] = [
			'@type' => 'ItemList',
			'itemListElement' => $items,
		];
	}

	return $payload;
}
