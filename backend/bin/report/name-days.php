<?php

/**
 * Report of the name days as Markdown (docs/jmeniny.md): coverage of both modes, differences between
 * the Slovak calendar of sk.wikipedia and the Slovak line of the cs.wikipedia day pages (listed,
 * never merged), and 10 random days of each mode with meaning and bearer. Fixes nothing.
 * Usage: php bin/report/name-days.php [seed, default 20261029]
 */

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use App\Model\Events\MonthDay;
use App\Model\NameDays\Country;
use App\Model\NameDays\NameDayRepository;
use App\Model\NameDays\NameDayService;
use App\Model\NameDays\NameInfoRepository;

mt_srand((int) ($argv[1] ?? 20261029));
$container = (new App\Bootstrap(getenv()))->bootConsoleApplication();
$nameDays = $container->getByType(NameDayRepository::class);
$infos = $container->getByType(NameInfoRepository::class);
$service = $container->getByType(NameDayService::class);
$date = fn(string $monthDay): string => MonthDay::fromString($monthDay)->dayPageTitle();

echo "# Jmeniny\n\n";
echo "Vygenerováno `backend/bin/report/name-days.php` (" . (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d') . ").\n";
echo "CZ: sekce „Svátky“ stránek dnů cs.wikipedia (stejná revize jako události), hlavní položka = první řádek seznamu „Česko“.\n";
echo "SK: věta „Meniny má …“ v úvodu stránek dnů sk.wikipedia (vlastní revize).\n";
echo "Význam: úvod článku o jménu na Wikipedii režimu (ověřeno přes Wikidata: instance křestního jména).\n";
echo "Nositel: Wikidata P735 + státní příslušnost (CZ: Česko/Československo, SK: Slovensko/Československo) + článek na Wikipedii režimu.\n\n";

echo "## Pokrytí\n\n| Režim | Dní se jmény | Hlavních jmen | Se zjištěným významem | Se známým nositelem |\n|---|---|---|---|---|\n";
foreach (Country::cases() as $country) {
	$days = $nameDays->stats($country);
	$known = $infos->stats($country);
	printf("| %s | %d z 366 | %d | %d | %d |\n", $country->label(), $days['days'], count($nameDays->mainNames($country)), $known['meaning'], $known['bearer']);
}

echo "\n## Slovenský kalendář: sk.wikipedia × řádek „Slovensko“ na cs.wikipedia\n\n";
echo "Aplikace používá jen sk.wikipedia; řádek z cs.wikipedia slouží jen k porovnání. Nic se neslučuje.\n\n";
$same = $differ = $onlySk = $onlyCs = $neither = 0;
$rows = [];
$bySource = $nameDays->slovakBySource();
foreach (MonthDay::all() as $day) {
	$pair = $bySource[$day->format()] ?? ['cs' => [], 'sk' => []];
	[$cs, $sk] = [$pair['cs'], $pair['sk']];
	if ($cs === [] && $sk === []) {
		$neither++;
	} elseif ($cs === []) {
		$onlySk++;
	} elseif ($sk === []) {
		$onlyCs++;
		$rows[] = [$day->format(), '–', implode(', ', $cs)];
	} elseif ($cs === $sk) {
		$same++;
	} else {
		$differ++;
		$rows[] = [$day->format(), implode(', ', $sk), implode(', ', $cs)];
	}
}
printf("Shodné: **%d** · rozdílné: **%d** · jen na sk.wikipedia: **%d** (cs.wikipedia slovenský řádek nemá) · jen na cs.wikipedia: **%d** · ani jedna: **%d**.\n\n", $same, $differ, $onlySk, $onlyCs, $neither);
echo "Dny, kde se jména liší nebo je má jen cs.wikipedia:\n\n| Datum | sk.wikipedia (používá se) | cs.wikipedia, řádek Slovensko |\n|---|---|---|\n";
foreach ($rows as [$monthDay, $sk, $cs]) {
	printf("| %s | %s | %s |\n", $date($monthDay), $sk, $cs);
}

echo "\n## Varianty jmen (ke kontrole)\n\n";
echo "Jen z dat: (a) článek o jménu přesměrovává na článek jiného jména téhož dne, (b) stejná položka Wikidat,\n";
echo "(c) položky spojuje P460 „said to be the same as“ nebo P1560 „jméno pro druhé pohlaví“. Hlavní je jméno uvedené dřív.\n\n";
echo "| Režim | Datum | Varianta | Hlavní jméno | Pravidlo |\n|---|---|---|---|---|\n";
$variantCount = 0;
foreach (MonthDay::all() as $day) {
	foreach (array_filter($service->forDay($day)) as $countryDays) {
		foreach ($countryDays->names as $entry) {
			if ($entry['variantOf'] !== null) {
				$variantCount++;
				printf("| %s | %s | %s | %s | %s |\n", $countryDays->country->label(), $day->dayPageTitle(), $entry['nameDay']->name, $entry['variantOf']['name'], $entry['variantOf']['rule']);
			}
		}
	}
}
printf("\nVariant celkem: **%d**.\n", $variantCount);

$days = MonthDay::all();
$sample = (array) array_rand($days, 10);
foreach (Country::cases() as $country) {
	printf("\n## Ukázka 10 náhodných dní – %s\n\n| Datum | %s | Význam | Známý nositel |\n|---|---|---|---|\n", $country->label(), $country === Country::Slovak ? 'Meniny má' : 'Svátek má');
	foreach ($sample as $index) {
		$day = $days[$index];
		$entries = $service->forDay($day)[$country->value]->names ?? [];
		if ($entries === []) {
			printf("| %s | – | – | – |\n", $day->dayPageTitle());
			continue;
		}
		printf(
			"| %s | %s | %s | %s |\n",
			$day->dayPageTitle(),
			implode(', ', array_map(fn($e) => $e['nameDay']->name, $entries)),
			implode(', ', array_map(fn($e) => $e['nameDay']->name . ': ' . (isset($e['info']->meaning) ? 'ano' : 'ne'), $entries)),
			implode(', ', array_map(fn($e) => $e['nameDay']->name . ': ' . ($e['info']->bearer->name ?? '–'), $entries)),
		);
	}
}
