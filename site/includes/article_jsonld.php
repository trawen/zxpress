<?php

declare(strict_types=1);

/**
 * Schema.org NewsArticle JSON-LD for electronic magazine (ezine) articles.
 */

require_once __DIR__ . '/screen_images.php';
require_once __DIR__ . '/authors_slugs.php';

/**
 * ISO-8601 timestamp for Schema.org date fields.
 */
function article_jsonld_iso8601(int $unixTs): string
{
	if ($unixTs <= 0) {
		return '';
	}

	return gmdate('c', $unixTs);
}

/**
 * Absolute URL from a site-relative path or already-absolute URL.
 */
function article_jsonld_absolute_url(string $origin, string $pathOrUrl): string
{
	$pathOrUrl = trim($pathOrUrl);
	if ($pathOrUrl === '') {
		return '';
	}
	if (preg_match('#^https?://#i', $pathOrUrl) === 1) {
		return $pathOrUrl;
	}

	return rtrim($origin, '/') . '/' . ltrim($pathOrUrl, '/');
}

/**
 * Display name for an authors row.
 *
 * @param array<string,mixed> $row
 */
function article_jsonld_author_name(array $row, bool $isEng): string
{
	if ($isEng) {
		$en = title_plain((string) ($row['name_en'] ?? ''));
		if ($en !== '') {
			return $en;
		}
	}
	$ru = title_plain((string) ($row['name_ru'] ?? ''));
	if ($ru !== '') {
		return $ru;
	}

	return title_plain((string) ($row['nickname'] ?? ''));
}

/**
 * Load linked authors for an article (authors_articles).
 *
 * @return list<array<string,mixed>>
 */
function article_jsonld_fetch_authors(mysqli $db, int $articleId): array
{
	if ($articleId <= 0) {
		return [];
	}

	$stmt = $db->prepare(
		'SELECT a.id, a.nickname, a.name_ru, a.name_en, a.slug_ru, a.slug_en '
		. 'FROM authors_articles aa '
		. 'INNER JOIN authors a ON a.id = aa.id_author '
		. 'WHERE aa.id_article = ? '
		. 'ORDER BY aa.id ASC'
	);
	if (!$stmt) {
		return [];
	}
	$stmt->bind_param('i', $articleId);
	$stmt->execute();
	$result = $stmt->get_result();
	$rows = [];
	while ($row = $result->fetch_assoc()) {
		$rows[] = $row;
	}
	$stmt->close();

	return $rows;
}

/**
 * Build schema.org NewsArticle payload (associative array).
 *
 * @param array<string,mixed> $article Must include title/title_eng; optional date, dt
 * @param array<string,mixed>|null $screens screens table row
 * @param list<array<string,mixed>> $authorRows authors rows
 * @param array<string,mixed> $press press row with title / public url fields
 */
function article_newsarticle_jsonld(
	string $origin,
	string $canonicalUrl,
	array $article,
	?array $screens,
	array $authorRows,
	array $press,
	string $pressPublicUrl,
	int $issueDateTs,
	bool $isEng,
	string $description = ''
): array {
	$headline = $isEng
		? title_plain((string) ($article['title_eng'] ?? ''))
		: title_plain((string) ($article['title'] ?? ''));
	if ($headline === '') {
		$headline = title_plain((string) ($article['title'] ?? ''));
	}
	if ($headline === '' && $isEng) {
		$headline = title_plain((string) ($article['title_eng'] ?? ''));
	}

	$articleDate = (int) ($article['date'] ?? 0);
	$articleDt = (int) ($article['dt'] ?? 0);

	$publishedTs = 0;
	foreach ([$issueDateTs, $articleDt, $articleDate] as $candidate) {
		if ($candidate > 0) {
			$publishedTs = $candidate;
			break;
		}
	}

	$modifiedTs = $publishedTs;
	foreach ([$articleDate, $articleDt, $issueDateTs] as $candidate) {
		if ($candidate > $modifiedTs) {
			$modifiedTs = $candidate;
		}
	}

	$images = [];
	$screenId = (int) ($screens['id'] ?? 0);
	if ($screenId > 0) {
		$images[] = article_jsonld_absolute_url($origin, screen_public_url($screenId));
	}

	$authors = [];
	foreach ($authorRows as $row) {
		$name = article_jsonld_author_name($row, $isEng);
		if ($name === '') {
			continue;
		}
		$person = [
			'@type' => 'Person',
			'name' => $name,
		];
		$authorUrl = authors_url($row, $isEng);
		if ($authorUrl !== '') {
			$person['url'] = article_jsonld_absolute_url($origin, $authorUrl);
		}
		$authors[] = $person;
	}

	if ($authors === []) {
		$pressName = title_plain((string) ($press['title_plain'] ?? $press['title'] ?? ''));
		if ($pressName !== '') {
			$org = [
				'@type' => 'Organization',
				'name' => $pressName,
			];
			if ($pressPublicUrl !== '') {
				$org['url'] = article_jsonld_absolute_url($origin, $pressPublicUrl);
			}
			$authors[] = $org;
		}
	}

	$payload = [
		'@context' => 'https://schema.org',
		'@type' => 'NewsArticle',
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
	];

	if ($images !== []) {
		$payload['image'] = $images;
	}

	$publishedIso = article_jsonld_iso8601($publishedTs);
	if ($publishedIso !== '') {
		$payload['datePublished'] = $publishedIso;
	}
	$modifiedIso = article_jsonld_iso8601($modifiedTs);
	if ($modifiedIso !== '') {
		$payload['dateModified'] = $modifiedIso;
	}

	if ($authors !== []) {
		$payload['author'] = $authors;
	}

	$description = title_plain($description);
	if ($description !== '') {
		$payload['description'] = $description;
	}

	$pressName = title_plain((string) ($press['title_plain'] ?? $press['title'] ?? ''));
	if ($pressName !== '') {
		$isPartOf = [
			'@type' => 'PublicationIssue',
			'isPartOf' => [
				'@type' => 'Periodical',
				'name' => $pressName,
			],
		];
		if ($pressPublicUrl !== '') {
			$isPartOf['isPartOf']['url'] = article_jsonld_absolute_url($origin, $pressPublicUrl);
		}
		$payload['isPartOf'] = $isPartOf;
	}

	return $payload;
}

/**
 * JSON string safe for embedding in <script type="application/ld+json">.
 *
 * @param array<string,mixed> $payload
 */
function article_newsarticle_jsonld_encode(array $payload): string
{
	$json = json_encode(
		$payload,
		JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS
	);
	if ($json === false) {
		return '{}';
	}

	return $json;
}
