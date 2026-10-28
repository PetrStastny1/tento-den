<?php

/**
 * Consistency of the database with the parser: for a sample of days, re-fetches HTML of exactly
 * the stored revision, parses it with the SAME parser, resolves links via the Action API and
 * compares them with event_articles. It does not verify that the parser itself finds every link –
 * for an independent rough check see compare-wikitext-links.php.
 * Usage: php bin/report/check-links.php [sample size, default 25] [seed, default 20261029]
 */

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use App\Core\Http\HttpClient;
use App\Model\Events\DayPageParser;
use App\Model\Events\EventText;
use App\Model\Events\MonthDay;
use App\Model\Import\ArticleInfoSource;
use App\Model\Import\ImportReportRepository;

$sampleSize = (int) ($argv[1] ?? 25);
mt_srand((int) ($argv[2] ?? 20261029));

$container = (new App\Bootstrap(getenv()))->bootConsoleApplication();
$report = $container->getByType(ImportReportRepository::class);
$http = $container->getByType(HttpClient::class);
$parser = $container->getByType(DayPageParser::class);
$articles = $container->getByType(ArticleInfoSource::class);

$okDays = $report->okDays();
$fixed = array_intersect_key($okDays, array_flip(['01-06', '04-17', '06-30', '12-14', '10-28']));
$others = array_diff_key($okDays, $fixed);
$sample = $fixed + array_intersect_key($others, array_flip((array) array_rand($others, min($sampleSize, count($others)))));

$events = $missing = $extra = $order = $notFound = $unavailable = 0;
foreach ($sample as $dayString => $revision) {
	$day = MonthDay::fromString($dayString);
	try {
		$response = $http->get('https://cs.wikipedia.org/api/rest_v1/page/html/' . rawurlencode(str_replace(' ', '_', $day->dayPageTitle())) . "/$revision");
	} catch (App\Core\Http\HttpException $e) {
		$response = null;
	}
	if ($response === null || $response->status !== 200 || $response->header('Content-Revision-Id') !== (string) $revision) {
		// a day that cannot be checked is a failed check, not a skipped one
		$unavailable++;
		echo "$dayString: HTML revize $revision nedostupné nebo nesouhlasí – kontrola selhala\n";
		continue;
	}
	$parsed = $parser->parse($response->body, $day)->events;
	$titles = array_values(array_unique(array_merge(...array_map(fn($event) => $event->linkTitles, $parsed))));
	$resolved = $titles === [] ? [] : $articles->fetch($titles);

	foreach ($parsed as $event) {
		$expected = [];
		foreach ($event->linkTitles as $title) {
			$article = $resolved[$title] ?? null;
			if ($article !== null && !in_array($article->pageId, $expected, true)) {
				$expected[] = $article->pageId;
			}
		}
		$stored = $report->linkedPageIds($day, $event->section->value, $event->year, EventText::hash($event->text));
		if ($stored === null) {
			$notFound++;
			continue;
		}
		$events++;
		$missing += count(array_diff($expected, $stored));
		$extra += count(array_diff($stored, $expected));
		$order += (int) ($stored !== $expected && !array_diff($expected, $stored) && !array_diff($stored, $expected));
	}
	echo "$dayString revize $revision: zkontrolováno\n";
}
printf(
	"Vzorek %d dní, %d událostí: chybějící odkazy %d, přebývající %d, jiné pořadí %d, událost nenalezena %d, nedostupná revize %d\n",
	count($sample),
	$events,
	$missing,
	$extra,
	$order,
	$notFound,
	$unavailable,
);
exit($missing + $extra + $order + $notFound + $unavailable === 0 ? 0 : 1);
