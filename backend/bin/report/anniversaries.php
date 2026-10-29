<?php

/**
 * Report for a human review (docs/vyroci.md): every round anniversary the app would show in the given
 * years – day, anniversary, event and why it counts as big – and a check of the exclusions
 * (each must match exactly one active event). Strike out unsuitable ones into
 * config/anniversary-exclusions.neon. Fixes nothing.
 * Usage: php bin/report/anniversaries.php [years, default 2026,2027]
 */

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use App\Model\Anniversaries\AnniversaryConfig;
use App\Model\Anniversaries\AnniversaryExclusions;
use App\Model\Events\MonthDay;
use App\Model\Events\Year;
use App\Model\Selection\DayService;

$years = array_map('intval', explode(',', $argv[1] ?? '2026,2027'));
$container = (new App\Bootstrap(getenv()))->bootConsoleApplication();
$dayService = $container->getByType(DayService::class);
$config = $container->getByType(AnniversaryConfig::class);
$exclusions = $container->getByType(AnniversaryExclusions::class);

echo "# Kulatá výročí ke kontrole\n\n";
echo "Vygenerováno `backend/bin/report/anniversaries.php` (" . (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d') . ").\n\n";
echo "Kulaté: 25, 50, 75, 100, 150, 200, 250, 300 a dál násobky 100 let. Nejvýš jedno denně, jen velká událost:\n";
echo "a) událost státního svátku nebo významného dne (zákon č. 245/2000 Sb.), nebo\n";
printf("b) hlavní článek je sám událostí velkého typu (Wikidata P31/P279*: %s), jeho datum na Wikidatech je v roce události a má aspoň **%d** jazykových verzí.\n\n",
	implode(', ', $config->eventClasses), $config->minSitelinks);
echo "Nevhodné škrtněte – zapíšou se do `backend/config/anniversary-exclusions.neon` (jen vyřazení; pak se ukáže další v pořadí, nebo nic).\n";

foreach ($years as $year) {
	$rows = [];
	foreach (MonthDay::all() as $day) {
		foreach ($dayService->day($day, null, $year)->cards as $card) {
			if ($card->anniversary !== null) {
				$rows[] = sprintf("| %s | **%s** | %s: %s | %s |", $day->dayPageTitle(), $card->anniversary->label(), Year::format($card->data->year),
					str_replace('|', '\|', $card->data->text), $card->anniversary->reason);
			}
		}
	}
	printf("\n## %d (%d)\n\n", $year, count($rows));
	echo $rows === [] ? "Žádné.\n" : "| Den | Výročí | Událost | Proč prošla |\n|---|---|---|---|\n" . implode("\n", $rows) . "\n";
}

echo "\n## Vyřazená výročí\n\n";
if ($exclusions->all() === []) {
	echo "Zatím žádná.\n";
}
$errors = 0;
foreach ($exclusions->all() as $exclusion) {
	$matches = array_filter($dayService->analyze($exclusion['day'])->events, fn($e) => $e->year === $exclusion['year'] && str_contains($e->text, $exclusion['text']));
	$ok = count($matches) === 1;
	$errors += (int) !$ok;
	printf("- %s %d „%s“ – %s%s\n", $exclusion['day']->dayPageTitle(), $exclusion['year'], $exclusion['text'], $exclusion['reason'], $ok ? '' : sprintf(' – **CHYBA: %d shod**', count($matches)));
}
exit($errors === 0 ? 0 : 1);
