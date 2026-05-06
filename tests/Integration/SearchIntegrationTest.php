<?php

declare(strict_types=1);

/**
 * Manticore search via search_query(); skips when index returns no hits (empty index).
 */
final class SearchIntegrationTest extends IntegrationTestCase
{
	public static function setUpBeforeClass(): void
	{
		self::requireIntegrationEnv();
	}

	public function testSearchQueryReturnsArrayStructure(): void
	{
		$r = search_query('spectrum', 'test1', 0, 5, SORT_RELEVANCE);
		self::assertArrayHasKey('total', $r);
		self::assertArrayHasKey('time', $r);
		self::assertArrayHasKey('matches', $r);
		if (($r['total'] ?? 0) === 0) {
			self::markTestSkipped('Manticore index empty or no matches; run indexer after DB seed (see docs/testing.md).');
		}
		self::assertIsArray($r['matches']);
	}
}
