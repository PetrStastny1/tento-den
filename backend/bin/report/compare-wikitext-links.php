<?php

/**
 * Independent rough check of the parser: counts links [[…]] in the wikitext of "Události" →
 * Česko / Svět (regexes, not the HTML parser) and compares them with event_articles per day.
 * Rough on purpose: red links, two links to one article and redirects make small differences
 * normal; only days with a large difference are printed. Fixes nothing.
 * Usage: php bin/report/compare-wikitext-links.php [min absolute difference, default 5]
 */

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use App\Model\Events\LinkFilter;
use App\Model\Events\MonthDay;
use App\Model\Import\ImportReportRepository;
use App\Model\Wikipedia\ActionApiClient;

$minDifference = (int) ($argv[1] ?? 5);
$container = (new App\Bootstrap(getenv()))->bootConsoleApplication();
$report = $container->getByType(ImportReportRepository::class);
$api = $container->getByType(ActionApiClient::class);
$filter = new LinkFilter; // only to drop years, dates and namespaces the same way

/** number of distinct article links per item, summed over the day; -1 when the section is missing */
$countLinks = function (string $wikitext) use ($filter): int {
	$text = (string) preg_replace(['~<!--.*?-->~s', '~<ref[^>]*/>~', '~<ref[^>]*>.*?</ref>~s'], '', $wikitext);
	if (!preg_match('~^==\s*Události\s*==\s*$(.*?)(?=^==[^=])~msu', $text, $m)) {
		return -1;
	}
	$links = 0;
	$inside = false;
	foreach (explode("\n", $m[1]) as $line) {
		if (preg_match('~^===\s*([^=]+?)\s*===~u', $line, $h)) {
			$inside = in_array($h[1], ['Česko', 'Svět'], true);
			continue;
		}
		if (!$inside || !str_starts_with($line, '*')) {
			continue;
		}
		preg_match_all('~\[\[([^\]|#]+)(?:[#|][^\]]*)?\]\]~u', $line, $found);
		$targets = [];
		foreach ($found[1] as $target) {
			$target = trim(str_replace('_', ' ', $target));
			if ($target === '' || preg_match('~^:?[a-z]{1,3}:~', $target) || !$filter->isArticleCandidate($target)) {
				continue; // interwiki (s:, en:) or year/date/namespace
			}
			$targets[mb_strtoupper(mb_substr($target, 0, 1)) . mb_substr($target, 1)] = true;
		}
		$links += count($targets);
	}
	return $links;
};

$okDays = $report->okDays();
$dbLinks = $report->linksPerDay();
$titles = [];
foreach (MonthDay::all() as $day) {
	$titles[$day->dayPageTitle()] = $day->format();
}

$rows = [];
foreach (array_chunk(array_keys($titles), 50) as $batch) {
	$data = $api->query(['action' => 'query', 'prop' => 'revisions', 'rvprop' => 'ids|content', 'rvslots' => 'main', 'titles' => implode('|', $batch)]);
	foreach ($data['query']['pages'] ?? [] as $page) {
		$day = $titles[$page['title']] ?? null;
		$revision = $page['revisions'][0] ?? null;
		if ($day === null || $revision === null) {
			continue;
		}
		$wikitextLinks = $countLinks((string) ($revision['slots']['main']['content'] ?? ''));
		$rows[$day] = [$revision['revid'], $wikitextLinks, $dbLinks[$day] ?? 0, $okDays[$day] ?? null];
	}
}
ksort($rows);

$large = 0;
$totalWikitext = $totalDb = 0;
foreach ($rows as $day => [$revision, $wikitextLinks, $db, $importedRevision]) {
	$totalWikitext += $wikitextLinks;
	$totalDb += $db;
	$difference = $wikitextLinks - $db;
	if (abs($difference) >= max($minDifference, (int) ceil(0.15 * max($wikitextLinks, $db)))) {
		$large++;
		printf(
			"%s: wikitext %d, DB %d, rozdíl %+d%s\n",
			$day,
			$wikitextLinks,
			$db,
			$difference,
			$revision === $importedRevision ? '' : " (stránka se od importu změnila: revize $importedRevision → $revision)",
		);
	}
}
printf("Dní: %d, odkazů ve wikitextu %d, v DB %d, dní s velkým rozdílem %d\n", count($rows), $totalWikitext, $totalDb, $large);
