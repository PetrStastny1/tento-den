<?php

/**
 * Quality report of the selection as Markdown (docs/kvalita.md): the five cards of random days
 * and the "golden set" of well-known anniversaries – whether they made it into their day's five,
 * and if not, why. Fixes nothing.
 * Usage: php bin/report/selection-quality.php [days, default 12] [seed, default 20261029]
 */

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use App\Model\Events\MonthDay;
use App\Model\Events\Year;
use App\Model\Selection\Candidate;
use App\Model\Selection\DaySelector;
use App\Model\Selection\DayService;

$dayCount = (int) ($argv[1] ?? 12);
mt_srand((int) ($argv[2] ?? 20261029));

$container = (new App\Bootstrap(getenv()))->bootConsoleApplication();
$dayService = $container->getByType(DayService::class);
$selector = $container->getByType(DaySelector::class);

$golden = [
	['10-28', 1918, 'cz', 'vznik Československa'],
	['11-17', 1989, 'cz', 'začátek sametové revoluce'],
	['01-01', 1993, 'cz', 'vznik samostatné České republiky'],
	['08-21', 1968, 'cz', 'invaze vojsk Varšavské smlouvy'],
	['07-20', 1969, 'world', 'přistání Apolla 11 na Měsíci'],
	['11-09', 1989, 'world', 'pád Berlínské zdi'],
	['04-12', 1961, 'world', 'let Jurije Gagarina'],
	['06-06', 1944, 'world', 'vylodění v Normandii'],
];

$short = fn(string $text): string => mb_strlen($text) > 140 ? mb_substr($text, 0, 139) . '…' : $text;
$section = fn(string $section): string => $section === 'cz' ? 'Česko' : 'Svět';

echo "# Kvalita výběru pětice\n\n";
echo "Vygenerováno `backend/bin/report/selection-quality.php` (" . (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d') . ").\n";
echo "Skóre = počet jazykových verzí hlavního článku (jinak nejlepšího neobecného odkazu), log-normalizované v rámci dne a sekce.\n\n";

echo "## Pětice pro $dayCount náhodných dní\n\n";
$days = MonthDay::all();
foreach ((array) array_rand($days, $dayCount) as $index) {
	$day = $days[$index];
	$analysis = $dayService->analyze($day);
	$chosen = $selector->select($analysis->candidates);
	printf("### %s (%d událostí: Česko %d, Svět %d)\n\n", $day->dayPageTitle(), count($analysis->candidates),
		count(array_filter($analysis->candidates, fn(Candidate $c) => $c->section->value === 'cz')),
		count(array_filter($analysis->candidates, fn(Candidate $c) => $c->section->value === 'world')));
	echo "| Rok | Sekce | Jazyků | Text | Hlavní článek |\n|---|---|---|---|---|\n";
	foreach ($chosen as $candidate) {
		$event = $analysis->events[$candidate->id];
		printf("| %s | %s | %d | %s | %s |\n", Year::format($event->year), $section($event->section->value), $analysis->rawSignals[$candidate->id],
			str_replace('|', '\|', $short($event->text)), $event->primaryTitle ?? '–');
	}
	echo "\n";
}

echo "## Zlatá sada\n\n";
echo "| Datum | Událost | V pětici | Proč ne |\n|---|---|---|---|\n";
foreach ($golden as [$dayString, $year, $sectionValue, $label]) {
	$day = MonthDay::fromString($dayString);
	$analysis = $dayService->analyze($day);
	$chosenIds = array_map(fn(Candidate $c) => $c->id, $selector->select($analysis->candidates));
	$matching = array_values(array_filter($analysis->candidates, fn(Candidate $c) => $c->year === $year && $c->section->value === $sectionValue));
	$inFive = array_values(array_filter($matching, fn(Candidate $c) => in_array($c->id, $chosenIds, true)));

	if ($matching === []) {
		$reason = "v sekci {$section($sectionValue)} není žádná aktivní událost roku $year";
	} elseif ($inFive !== []) {
		$reason = '';
	} else {
		usort($matching, fn(Candidate $a, Candidate $b) => $b->score <=> $a->score);
		$best = $matching[0];
		$sameSection = array_values(array_filter($analysis->candidates, fn(Candidate $c) => $c->section === $best->section));
		usort($sameSection, fn(Candidate $a, Candidate $b) => [$b->score, $a->id] <=> [$a->score, $b->id]);
		$rank = array_search($best, $sameSection, true) + 1;
		$event = $analysis->events[$best->id];
		$reason = sprintf(
			'nejlepší událost roku má skóre %.2f (%d jazyků, hlavní článek: %s), %d. z %d v sekci%s%s',
			$best->score,
			$analysis->rawSignals[$best->id],
			$event->primaryTitle ?? 'žádný',
			$rank,
			count($sameSection),
			$best->boringReason === null ? '' : "; filtr nudy: {$best->boringReason}",
			in_array($best->century(), array_map(fn(int $id) => $analysis->events[$id]->year > 0 ? intdiv($analysis->events[$id]->year - 1, 100) + 1 : 0, $chosenIds), true) ? '; století už v pětici je' : '',
		);
	}
	printf("| %s %d | %s | %s | %s |\n", $day->dayPageTitle(), $year, $label, $inFive !== [] ? 'ano' : 'ne', $reason);
}
