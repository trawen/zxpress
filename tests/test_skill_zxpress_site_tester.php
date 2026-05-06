<?php
/**
 * Guardrails test for zxpress-site-tester skill content.
 * Run: php tests/test_skill_zxpress_site_tester.php
 */

$passed = 0;
$failed = 0;

function test_case(string $name, bool $condition): void
{
	global $passed, $failed;
	if ($condition) {
		$passed++;
	} else {
		$failed++;
		echo "FAIL: {$name}\n";
	}
}

$skillPath = __DIR__ . '/../.cursor/skills/zxpress-site-tester/SKILL.md';
$matrixPath = __DIR__ . '/../.cursor/skills/zxpress-site-tester/references/TEST-MATRIX.md';

test_case('skill file exists', file_exists($skillPath));
test_case('matrix file exists', file_exists($matrixPath));

$skill = file_exists($skillPath) ? file_get_contents($skillPath) : '';
$matrix = file_exists($matrixPath) ? file_get_contents($matrixPath) : '';

test_case('skill: has infra_unreachable classification', strpos($skill, 'infra_unreachable') !== false);
test_case('skill: has local/remote target classification', strpos($skill, 'local target') !== false && strpos($skill, 'remote') !== false);
test_case('skill: forbids /aif-fix targets for infra-only failures', strpos($skill, 'do not produce `/aif-fix` targets from `infra_unreachable` findings') !== false);
test_case('skill: requires explicit BASE_URL', strpos($skill, 'every run must pass `BASE_URL=...` explicitly') !== false);
test_case('skill: defines remote log scope as n/a_remote', strpos($skill, 'n/a_remote') !== false);
test_case('skill: comments coverage points to book_articles surface', strpos($skill, 'book_articles.php?id=1') !== false);
test_case('skill: log window standardized to 10m', strpos($skill, '--since 10m') !== false);
test_case('skill: output includes Coverage map section', strpos($skill, '**Coverage map**') !== false);
test_case('skill: documents layout pass spec', strpos($skill, 'layout-anchors.spec.ts') !== false);
test_case('skill: documents a11y spec', strpos($skill, 'a11y-critical-pages.spec.ts') !== false);
test_case('skill: admin pass uses admin-surfaces.spec.ts', strpos($skill, 'admin-surfaces.spec.ts') !== false);
test_case('skill: focus modes include layout and a11y', strpos($skill, '`layout`') !== false && strpos($skill, '`a11y`') !== false);

test_case('matrix: comments flow references book_articles route', strpos($matrix, '/book_articles.php?id=1') !== false);
test_case('matrix: login flow references hyperjump route', strpos($matrix, '/hyperjump.php') !== false);
test_case('matrix: remote target log scope guidance present', strpos($matrix, 'n/a_remote') !== false);
test_case('matrix: log window standardized to 10m', strpos($matrix, '--since 10m') !== false);
test_case('matrix: references site-routes manifest', strpos($matrix, 'site-routes.ts') !== false);
test_case('matrix: e2e comments captcha testid documented', strpos($matrix, 'e2e-comments-captcha') !== false);
test_case('matrix: coverage levels table present', strpos($matrix, '| `routes` |') !== false && strpos($matrix, '| `a11y` |') !== false);

$total = $passed + $failed;
echo "\n" . str_repeat('=', 40) . "\n";
echo "Skill guardrails tests: {$passed}/{$total} passed";
if ($failed > 0) {
	echo " ({$failed} FAILED)";
}
echo "\n";
exit($failed > 0 ? 1 : 0);
