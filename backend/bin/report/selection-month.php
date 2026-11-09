<?php

/**
 * Statistics of the selection for one month: age of the cards, politics, sport, repeated types and
 * days that had to reach for excluded events. Without a year, like docs/kvalita.md (no anniversaries).
 * Usage: php bin/report/selection-month.php [month, default 11]
 */

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use App\Model\Events\MonthDay;
use App\Model\Selection\Candidate;
use App\Model\Selection\DayService;

const PoliticalReasons = ['vláda', 'jmenování do funkce', 'volby', 'běžná smlouva', 'návštěva státníka', 'zasedání a usnesení'];
const PoliticalTypes = ['vláda', 'volby', 'smlouva'];

$month = (int) ($argv[1] ?? 11);
$container = (new App\Bootstrap(getenv()))->bootConsoleApplication();
$dayService = $container->getByType(DayService::class);

$days = array_values(array_filter(MonthDay::all(), fn(MonthDay $d): bool => $d->month === $month));
$totals = ['cards' => 0, 'modern' => 0, 'middle' => 0, 'old' => 0, 'politics' => 0, 'sport' => 0, 'czech' => 0, 'excluded' => 0];
$reachedDays = $duplicateDays = $shortDays = [];

echo "| Den | Karet | do 1800 | 19. st. | 1900+ | Česko | Vyřazené (mimo povinné) | Opakovaný typ |\n|---|---|---|---|---|---|---|---|\n";
foreach ($days as $day) {
	$analysis = $dayService->analyze($day);
	$byId = [];
	foreach ($analysis->candidates as $candidate) {
		$byId[$candidate->id] = $candidate;
	}
	$cards = $dayService->day($day)->cards;
	$chosen = array_map(fn($card): Candidate => $byId[$card->data->id], $cards);
	$forcedIds = array_map(fn($card): int => $card->data->id, array_filter($cards, fn($card): bool => $card->stateDay !== null));

	$typeCounts = array_count_values(array_filter(array_map(fn(Candidate $c): ?string => $c->type, $chosen), fn(?string $t): bool => $t !== null));
	$repeated = array_keys(array_filter($typeCounts, fn(int $count): bool => $count > 1));
	$reached = array_filter($chosen, fn(Candidate $c): bool => !in_array($c->id, $forcedIds, true) && $c->excludedReason !== null);

	$old = count(array_filter($chosen, fn(Candidate $c): bool => $c->isBefore1800()));
	$modern = count(array_filter($chosen, fn(Candidate $c): bool => $c->isModern()));
	$czech = count(array_filter($chosen, fn(Candidate $c): bool => $c->section->value === 'cz'));
	$totals['cards'] += count($chosen);
	$totals['old'] += $old;
	$totals['modern'] += $modern;
	$totals['middle'] += count($chosen) - $old - $modern;
	$totals['czech'] += $czech;
	$totals['excluded'] += count($reached);
	$totals['politics'] += count(array_filter($chosen, fn(Candidate $c): bool => in_array($c->excludedReason, PoliticalReasons, true) || in_array($c->type, PoliticalTypes, true)));
	$totals['sport'] += count(array_filter($chosen, fn(Candidate $c): bool => $c->excludedReason === 'menší sport' || $c->type === 'sport'));
	if ($reached !== [] || $repeated !== []) {
		$reachedDays[] = $day->day;
	}
	if ($repeated !== []) {
		$duplicateDays[] = $day->day;
	}
	if (count($chosen) < 10) {
		$shortDays[] = $day->day;
	}
	printf("| %d. | %d | %d | %d | %d | %d | %s | %s |\n", $day->day, count($chosen), $old, count($chosen) - $old - $modern, $modern, $czech,
		implode(', ', array_map(fn(Candidate $c): string => "{$c->year}: {$c->excludedReason}", $reached)) ?: '–',
		implode(', ', $repeated) ?: '–');
}

printf("\nKaret: %d. Z let 1900+: %d, 19. století: %d, před 1800: %d. Česko: %d.\n", $totals['cards'], $totals['modern'], $totals['middle'], $totals['old'], $totals['czech']);
printf("Politika: %d, sport: %d (podle pravidel typů a vyřazení).\n", $totals['politics'], $totals['sport']);
printf("Dní, které sáhly po vyřazených nebo opakovaném typu: %d (%s); vyřazených karet: %d.\n", count($reachedDays), implode(', ', $reachedDays), $totals['excluded']);
printf("Dní s opakovaným typem: %d (%s).\n", count($duplicateDays), implode(', ', $duplicateDays));
printf("Dní s méně než 10 kartami: %d (%s).\n", count($shortDays), implode(', ', $shortDays));
