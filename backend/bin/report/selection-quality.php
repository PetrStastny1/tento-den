<?php

/**
 * Quality report of the selection as Markdown (docs/kvalita.md): the ten cards of random days
 * and the "golden set" of well-known anniversaries – whether they made it into their day's ten,
 * and if not, why – and all state holidays and significant days (Act No. 245/2000 Coll.): was the
 * commemorated event found, or the article summary stored. Fixes nothing.
 * Usage: php bin/report/selection-quality.php [days, default 12] [seed, default 20261029]
 */

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use App\Model\Events\MonthDay;
use App\Model\Events\Year;
use App\Model\Selection\Candidate;
use App\Model\Selection\Card;
use App\Model\Selection\DayService;
use App\Model\StateDays\StateDayCheck;

$dayCount = (int) ($argv[1] ?? 12);
mt_srand((int) ($argv[2] ?? 20261029));

$container = (new App\Bootstrap(getenv()))->bootConsoleApplication();
$dayService = $container->getByType(DayService::class);

// day, year, a fragment identifying exactly one event text, label
$golden = [
	['10-28', 1918, 'vyhlášen samostatný československý stát', 'vznik Československa'],
	['11-17', 1989, 'zásah Veřejné bezpečnosti proti studentské demonstraci', 'začátek sametové revoluce'],
	['01-01', 1993, 'Vznikly Česká republika a Slovenská republika', 'vznik samostatné České republiky'],
	['08-21', 1968, 'Vojenský zásah armád pěti států Varšavské smlouvy', 'invaze vojsk Varšavské smlouvy'],
	['07-20', 1969, 'dosedl přistávací modul Apolla 11', 'přistání Apolla 11 na Měsíci'],
	['11-09', 1989, 'Pád Berlínské zdi', 'pád Berlínské zdi'],
	['04-12', 1961, 'Gagarin jako první pobýval ve vesmíru', 'let Jurije Gagarina'],
	['06-06', 1944, 'Začalo vylodění v Normandii', 'vylodění v Normandii'],
];

$short = fn(string $text): string => mb_strlen($text) > 140 ? mb_substr($text, 0, 139) . '…' : $text;
$section = fn(string $section): string => $section === 'cz' ? 'Česko' : 'Svět';

echo "# Kvalita výběru karet dne\n\n";
echo "Vygenerováno `backend/bin/report/selection-quality.php` (" . (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d') . ").\n";
echo "Skóre = počet jazykových verzí hlavního článku (jinak nejlepšího neobecného odkazu), log-normalizované v rámci dne a sekce.\n\n";

echo "## Desítka karet pro $dayCount náhodných dní\n\n";
$days = MonthDay::all();
foreach ((array) array_rand($days, $dayCount) as $index) {
	$day = $days[$index];
	$analysis = $dayService->analyze($day);
	$chosen = $dayService->day($day)->cards; // as the API shows it, commemorated events first
	printf("### %s (%d událostí: Česko %d, Svět %d)\n\n", $day->dayPageTitle(), count($analysis->candidates),
		count(array_filter($analysis->candidates, fn(Candidate $c) => $c->section->value === 'cz')),
		count(array_filter($analysis->candidates, fn(Candidate $c) => $c->section->value === 'world')));
	echo "| Rok | Sekce | Jazyků | Text | Hlavní článek |\n|---|---|---|---|---|\n";
	foreach ($chosen as $card) {
		$event = $card->data;
		printf("| %s | %s | %d | %s%s | %s |\n", Year::format($event->year), $section($event->section->value), $analysis->rawSignals[$event->id],
			$card->stateDay === null ? '' : "**{$card->stateDay->type->label()}: {$card->stateDay->name}** – ",
			str_replace('|', '\|', $short($event->text)), $event->primaryTitle ?? '–');
	}
	echo "\n";
}

echo "## Zlatá sada\n\n";
echo "| Datum | Událost | V desítce | Proč ne v desítce |\n|---|---|---|---|\n";
$goldenHits = 0;
foreach ($golden as [$dayString, $year, $fragment, $label]) {
	$day = MonthDay::fromString($dayString);
	$analysis = $dayService->analyze($day);
	$chosenIds = array_map(fn(Card $c) => $c->data->id, $dayService->day($day)->cards);
	$matching = array_values(array_filter(
		$analysis->candidates,
		fn(Candidate $c) => $c->year === $year && str_contains($analysis->events[$c->id]->text, $fragment),
	));
	$inTen = count($matching) === 1 && in_array($matching[0]->id, $chosenIds, true);
	$goldenHits += (int) $inTen;

	if (count($matching) !== 1) {
		$reason = sprintf('definice nesedí: %d aktivních událostí roku %d obsahuje „%s“', count($matching), $year, $fragment);
	} elseif ($inTen) {
		$reason = '';
	} else {
		$best = $matching[0];
		$sameSection = array_values(array_filter($analysis->candidates, fn(Candidate $c) => $c->section === $best->section));
		usort($sameSection, fn(Candidate $a, Candidate $b) => [$b->score, $a->id] <=> [$a->score, $b->id]);
		$rank = array_search($best, $sameSection, true) + 1;
		$event = $analysis->events[$best->id];
		$chosenScores = array_map(fn(Candidate $c) => $c->score, array_filter($analysis->candidates, fn(Candidate $c) => in_array($c->id, $chosenIds, true)));
		$reason = sprintf(
			'skóre %.2f (%d jazyků, hlavní článek: %s), %d. z %d v sekci; nejslabší karta v desítce má %.2f%s',
			$best->score,
			$analysis->rawSignals[$best->id],
			$event->primaryTitle ?? 'žádný',
			$rank,
			count($sameSection),
			$chosenScores === [] ? 0 : min($chosenScores),
			$best->boringReason === null ? '' : "; filtr nudy: {$best->boringReason}",
		);
	}
	printf("| %s %d | %s | %s | %s |\n", $day->dayPageTitle(), $year, $label, $inTen ? 'ano' : 'ne', $reason);
}
printf("\nV desítce svého dne: **%d z %d**.\n", $goldenHits, count($golden));

echo "\n## Státní svátky a významné dny\n\n";
echo "Podle zákona č. 245/2000 Sb. ([aktuální znění](https://www.zakonyprolidi.cz/cs/2000-245)), `backend/config/state-days.neon`.\n";
echo "Den s historickou událostí: úryvek musí najít přesně jednu aktivní událost a ta musí být v desítce první.\n";
echo "Den bez události: samostatná karta s úvodem článku (pohyblivé svátky jen v roce, kdy na daný den připadají).\n\n";
echo "| Datum | Oficiální název | Typ | Událost / článek | Nalezeno | Poznámka |\n|---|---|---|---|---|---|\n";
$errors = 0;
foreach ($container->getByType(StateDayCheck::class)->run() as $check) {
	$stateDay = $check['stateDay'];
	$date = $stateDay->date !== null
		? $stateDay->date->dayPageTitle()
		: sprintf('Velikonoce %+d (%s)', $stateDay->easterOffset, $stateDay->dateIn((int) date('Y'))->format());
	if ($stateDay->hasEvent()) {
		$what = sprintf('%d: „%s“', $stateDay->eventYear, $stateDay->eventText);
		$note = match (true) {
			$check['ok'] => 'v desítce první',
			$check['matches'] === 0 => '**CHYBA: úryvek nenašel žádnou aktivní událost**',
			$check['matches'] > 1 => sprintf('**CHYBA: úryvek našel %d událostí**', $check['matches']),
			default => '**CHYBA: událost není v desítce první**',
		};
	} else {
		$what = sprintf('článek „%s“', $stateDay->article);
		$note = $check['ok'] ? sprintf('úvod z revize %d', $check['summary']?->revisionId) : '**CHYBA: úvod článku není stažený**';
	}
	$errors += (int) !$check['ok'];
	printf("| %s | %s | %s | %s | %s | %s |\n", $date, $stateDay->name, $stateDay->type->label(), str_replace('|', '\\|', $what), $check['ok'] ? 'ano' : 'ne', $note);
}
printf("\nChyb: **%d**.\n", $errors);
