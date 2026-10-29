<?php

/**
 * Report for a human review (docs/nositele.md): every name of both modes with its chosen known
 * bearer – name, occupations (Wikidata P106) and the link. Strike out unsuitable ones into
 * config/name-bearer-exclusions.neon and run make refresh-names ALL=1. Fixes nothing.
 * Usage: php bin/report/name-bearers.php
 */

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use App\Model\NameDays\Country;
use App\Model\NameDays\NameDayRepository;
use App\Model\NameDays\NameInfoRepository;
use App\Model\Wikipedia\WikipediaUrl;

$container = (new App\Bootstrap(getenv()))->bootConsoleApplication();
$nameDays = $container->getByType(NameDayRepository::class);
$infos = $container->getByType(NameInfoRepository::class);

echo "# Známí nositelé jmen ke kontrole\n\n";
echo "Vygenerováno `backend/bin/report/name-bearers.php` (" . (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d') . ").\n\n";
echo "Pravidlo: Wikidata P735 (křestní jméno) = položka jména, státní příslušnost (P27) Česko/Československo (CZ),\n";
echo "Slovensko/Československo (SK), článek na Wikipedii režimu. Vyřazeno: povolání z oblasti erotiky a pornografie;\n";
echo "odsouzení (P1399) bez povolání z politiky, vědy, umění, literatury, sportu, filmu, hudby či historické role.\n";
echo "Pořadí: tato pole, pak jméno na začátku popisku, pak počet jazykových verzí (podrobně v docs/rozhodnuti.md).\n\n";
echo "Nevhodné škrtněte – zapíšou se do `backend/config/name-bearer-exclusions.neon` (země, jméno, položka Wikidat) a vybere se další v pořadí, nebo nikdo.\n";

foreach (Country::cases() as $country) {
	$names = $nameDays->mainNames($country);
	$known = $infos->findByNames($country, $names);
	$withBearer = array_filter($known, fn($info) => $info->bearer !== null);
	printf("\n## %s (%d jmen, nositel u %d, bez dohledání %d)\n\n", $country->label(), count($names), count($withBearer), count($names) - count($known));
	echo "| Jméno | Nositel | Povolání | Wikidata | Článek |\n|---|---|---|---|---|\n";
	foreach ($names as $name) {
		$bearer = $known[$name]->bearer ?? null;
		if ($bearer === null) {
			printf("| %s | – | | | |\n", $name);
			continue;
		}
		printf("| %s | %s | %s | %s | [%s](%s) |\n", $name, $bearer->name, $bearer->occupations ?? '', $bearer->wikidataItem,
			$country->language() . '.wikipedia', WikipediaUrl::article($bearer->article, $country->language()));
	}
}
