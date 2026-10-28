<?php

/**
 * Statistics of the imported events and an offline recomputation of the primary article
 * from event_articles + config/generic-articles.neon (proves no re-download is needed).
 * Usage: php bin/report/import-stats.php
 */

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use App\Model\Events\PrimaryArticleSelector;
use App\Model\Import\ImportReportRepository;

$container = (new App\Bootstrap(getenv()))->bootConsoleApplication();
$report = $container->getByType(ImportReportRepository::class);
$selector = $container->getByType(PrimaryArticleSelector::class);

echo 'Dny podle stavu: ', json_encode($report->dayStatusCounts()), "\n";
foreach ($report->eventCounts() as $row) {
	echo "Události {$row['status']} {$row['section']}: {$row['events']}\n";
}

$median = function (array $values): float {
	sort($values);
	$n = count($values);
	return $n % 2 ? $values[intdiv($n, 2)] : ($values[$n / 2 - 1] + $values[$n / 2]) / 2;
};
$okDays = array_fill_keys(array_keys($report->okDays()), 0);
$perDay = $report->activeEventsPerDay();
foreach (['cz', 'world'] as $section) {
	$counts = array_merge($okDays, $perDay[$section] ?? []);
	if ($counts === []) {
		continue;
	}
	$min = min($counts);
	printf(
		"Aktivní %s na den: medián %s, minimum %d (%s)\n",
		$section,
		$median(array_values($counts)),
		$min,
		implode(', ', array_keys(array_filter($counts, fn(int $n): bool => $n === $min))),
	);
}
$poor = array_filter(array_merge($okDays, $perDay['cz'] ?? []), fn(int $n): bool => $n < 2);
echo 'Dny s méně než 2 aktivními českými událostmi: ', implode(', ', array_map(fn($day, $n) => "$day ($n)", array_keys($poor), $poor)), "\n";

$withoutPrimary = $report->eventsWithoutPrimary();
echo 'Bez hlavního článku: ', json_encode($withoutPrimary['bySection']), ", z toho bez odkazu na existující článek: {$withoutPrimary['withoutAnyArticle']}\n";

$mismatches = 0;
$events = $report->eventArticles();
foreach ($events as $id => $event) {
	$positions = array_column($event['articles'], 'position');
	$primary = $selector->select(array_column($event['articles'], 'article'));
	$stored = array_values(array_filter($event['articles'], fn(array $link): bool => $link['isPrimary']));
	$consistent = $positions === ($positions === [] ? [] : range(1, count($positions)))
		&& ($primary === null
			? $stored === [] && !$event['hasPrimary']
			: count($stored) === 1 && $stored[0]['article']->pageId === $primary->pageId && $event['hasPrimary']);
	if (!$consistent) {
		$mismatches++;
		echo "  nesoulad u události #$id\n";
	}
}
printf("Přepočet hlavního článku jen z DB: %d událostí, nesouladů %d\n", count($events), $mismatches);
exit($mismatches === 0 ? 0 : 1);
