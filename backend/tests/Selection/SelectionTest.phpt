<?php

/**
 * Scoring, event classification, sensitivity and the selection of ten cards (pure logic).
 */

declare(strict_types=1);

use App\Model\Events\Section;
use App\Model\Selection\BoringFilter;
use App\Model\Selection\Candidate;
use App\Model\Selection\DaySelector;
use App\Model\Selection\EventClassifier;
use App\Model\Selection\EventScorer;
use App\Model\Selection\SensitivityClassifier;
use App\Model\Selection\TextRules;
use Tester\Assert;
use Tests\Support\TestContainer;

require __DIR__ . '/../bootstrap.php';


function candidate(
	int $id,
	Section $section,
	int $year,
	float $score,
	?string $boring = null,
	?string $excluded = null,
	?string $type = null,
	bool $remarkable = false,
): Candidate {
	return new Candidate($id, $section, $year, $score, $boring, $excluded, $type, $remarkable);
}


/**
 * @param list<Candidate> $chosen
 * @return list<int>
 */
function ids(array $chosen): array
{
	return array_map(fn(Candidate $c): int => $c->id, $chosen);
}


test('signal: primary article, otherwise the best non-generic link, otherwise zero', function () {
	$scorer = new EventScorer;
	Assert::same(120, $scorer->rawSignal(120, false, [300]));
	Assert::same(300, $scorer->rawSignal(null, false, [12, 300, null]));
	Assert::same(300, $scorer->rawSignal(500, true, [300])); // a generic primary article does not count
	Assert::same(0, $scorer->rawSignal(null, false, [null]));
	Assert::same(0, $scorer->rawSignal(null, false, []));
});


test('normalization is logarithmic within one group', function () {
	$scores = (new EventScorer)->normalize([1 => 255, 2 => 15, 3 => 0]);
	Assert::same(1.0, $scores[1]);
	Assert::same(0.5, round($scores[2], 2));
	Assert::same(0.0, $scores[3]);
	Assert::same([4 => 0.0], (new EventScorer)->normalize([4 => 0]));
	Assert::same([], (new EventScorer)->normalize([]));
});


test('Czech events are not compared with world ones: each section is normalized on its own', function () {
	$scorer = new EventScorer;
	Assert::same(1.0, $scorer->normalize([1 => 8])[1]);      // Vznik Československa, 8 languages
	Assert::same(1.0, $scorer->normalize([2 => 151])[2]);    // Socha Svobody, 151 languages
});


test('dull rules from the configuration: matching and deliberately not matching texts', function () {
	$filter = TestContainer::create()->getByType(BoringFilter::class);
	$dull = [
		['sportovní výsledek', 'Sparta porazila Slavii v utkání ligy 3:1.'],
		['skóre zápasu', 'Finále skončilo výsledkem 2:0.'],
		['otevření obchodu', 'V Brně byl otevřen první supermarket.'],
		['otevření stavby', 'Otevřen Švehlův most přes Lužnici v Táboře.'],
		['otevření stavby', 'Linka C pražského metra byla prodloužena o úsek Kačerov–Háje.'],
		['přejmenování místa', 'Dosavadní zookoutek ve Dvoře Králové nad Labem byl po pěti letech existence přejmenován na Východočeskou zoologickou zahradu.'],
		['výroba a představení modelu', 'Automobilka Škoda Auto vyrobila poslední exemplář modelové řady Škoda Octavia první generace.'],
		['výroba a představení modelu', 'Automobilka Škoda Auto představila veřejnosti čtvrtou generaci svého nejprodávanějšího modelu Škoda Octavia.'],
		['výroba a představení modelu', 'AZNP Mladá Boleslav zahájily sériovou výrobu osobního automobilu Škoda 105/120.'],
		['výroba a představení modelu', 'Škoda Auto vyrobila miliontý vůz typové řady Škoda Favorit.'],
		['výroba a představení modelu', 'V NDR začala výroba vozů značky Trabant'],
		['výroba a představení modelu', 'Na českém trhu se začal prodávat malý kompaktní automobil Škoda Fabia.'],
		['soukromý sňatek', 'Stejnojmenný otec kantora a skladatele Jakuba Jany Ryby se oženil s Rozalií Karníkovou, dcerou učitele Jana Karníka.'],
		['vydání softwaru', 'Byla vydána nová verze komunikačního programu ICQ pod názvem ICQ 6.'],
		['drobná nehoda', 'Při srážce dvou tramvají se zranilo 12 lidí.'],
	];
	foreach ($dull as [$rule, $text]) {
		Assert::same($rule, $filter->reason($text), $text);
	}
	foreach ([
		'V Praze byl vyhlášen samostatný československý stát.',
		'Ve 23:59 končí existence Československa.',
		'Při srážce auta a dvou tramvají zahynul lékař Josef Rejsek.', // a death is not a minor accident
		'Začalo vysílat Radio Proglas.',
		'Konaly se první novodobé olympijské hry.',
		'Stát jihovýchodní Asie Siam byl přejmenován na Thajské království.', // a state is not a place
		'Masakr v Úsobské ulici si vyžádal 16 obětí; ulice byla později přejmenována na ulici Obětí 6. května.',
		'Anglický král Jindřich VIII. se oženil s Annou Boleynovou.', // a royal marriage is politics
		'První občanský sňatek v Království českém.',
		// firsts of their kind stay, and so does a product that is not a vehicle or a race won on one
		'Mladoboleslavská společnost Laurin & Klement představila veřejnosti motocykl Laurin & Klement Typ 1, první samohybný stroj vlastní konstrukce.',
		'První českomoravská továrna na stroje v Libni vyrobila první českou lokomotivu.',
		'Sovětský svaz zahájil výrobu útočné pušky AK-47.',
		'Zvítězil český závodník Václav Vondřich na motocyklu Slavia, vyrobeném automobilkou Laurin & Klement.',
	] as $text) {
		Assert::null($filter->reason($text), $text);
	}
	// a world or European title is a big thing, a cup match is not
	Assert::null($filter->reason('Českoslovenští házenkáři se stali ve Švédsku mistry světa, když ve finále MS porazili Dánsko 14:11.'));
	Assert::same('skóre zápasu', $filter->reason('Ve finále poháru porazila Sparta Baník 3:1.'));
});


test('excluded: routine politics and minor sport, the examples that must go', function () {
	$classifier = TestContainer::create()->getByType(EventClassifier::class);
	$excluded = [
		'Prezident České republiky Václav Klaus opět pověřil Mirka Topolánka sestavením vlády České republiky.' => 'vláda',
		'Prezident Edvard Beneš jmenoval druhou vládu Zdeňka Fierlingera.' => 'vláda',
		'Golda Meirová jmenována izraelskou velvyslankyní v SSSR.' => 'jmenování do funkce',
		'V parlamentních volbách ve Španělsku zvítězila Socialistická strana.' => 'volby',
		'Georges Pompidou byl zvolen prezidentem Francie.' => 'volby',
		'Joe Biden byl zvolen 46. prezidentem Spojených států amerických.' => 'volby',
		'Franklin Delano Roosevelt byl znovuzvolen americkým prezidentem.' => 'volby',
		'Miroslav Kalousek se stal předsedou KDU-ČSL.' => 'jmenování do funkce',
		'Premiérem Bulharska se stal Todor Živkov.' => 'jmenování do funkce',
		'Federation Cup v Praze: Kateřina Siniaková porazila Američanku.' => 'menší sport',
		'Švédsko a Prusko podepsali Hamburskou smlouvu.' => 'běžná smlouva',
		'Zasedání ÚV KSČ přitvrdilo v boji za kolektivizaci zemědělství.' => 'zasedání a usnesení',
		'Český ženský tenisový tým v Moskvě vyhrál Fed Cup.' => 'menší sport',
		'61. Davisův pohár vyhrály Spojené státy nad Rumunskem.' => 'menší sport',
		'Slovenský parlament ratifikoval Lisabonskou smlouvu.' => 'ratifikace smlouvy',
	];
	foreach ($excluded as $text => $rule) {
		Assert::same($rule, $classifier->excludedReason($text), $text);
	}
});


test('a treaty with an article of its own is not routine, a ratification or an unnamed treaty is', function () {
	$classifier = TestContainer::create()->getByType(EventClassifier::class);
	Assert::null($classifier->excludedReason(
		'Byla podepsána Rapallská smlouva (1922) mezi Německem a Sovětským Ruskem.',
		['Rapallská smlouva (1922)', 'Německo', 'Sovětský svaz'],
	));
	Assert::null($classifier->excludedReason('Ve Vídni podepsána Rakouská státní smlouva.', ['Vídeň', 'Rakouská státní smlouva']));
	Assert::same('běžná smlouva', $classifier->excludedReason('Uzavřena obchodní smlouva mezi Kubou a SSSR.', ['Kuba', 'Sovětský svaz']));
	Assert::same('ratifikace smlouvy', $classifier->excludedReason('Slovenský parlament ratifikoval Lisabonskou smlouvu.', ['Slovensko', 'Lisabonská smlouva']));
	Assert::same('vláda', $classifier->excludedReason('Prezident jmenoval druhou vládu.', ['Opoziční smlouva'])); // only the treaty rule has the exception
});


test('a visit is not excluded, only one visit a day', function () {
	$classifier = TestContainer::create()->getByType(EventClassifier::class);
	$nixon = 'Prezident USA Richard Nixon navštívil Čínskou lidovou republiku, aby stabilizoval čínsko-americké vztahy.';
	Assert::null($classifier->excludedReason($nixon));
	Assert::same('návštěva', $classifier->type($nixon));
});


test('9. 11.: the Fed Cup final of 2014 is minor sport and does not get in through the Czech quota', function () {
	$classifier = TestContainer::create()->getByType(EventClassifier::class);
	$text = 'Fed Cup v Praze, 52. ročník ženské tenisové týmové soutěže vyhrály Češky nad Němkami, když Petra Kvitová porazila Němku Angelique Kerberovou za stavu 3:0.';
	$reason = $classifier->excludedReason($text, ['Fed Cup 2014']);
	Assert::same('menší sport', $reason);

	// the shape of the day: two Czech events only, the Fed Cup with the higher score
	$candidates = [
		candidate(1, Section::Czech, 2014, 0.65, excluded: $reason, type: $classifier->type($text)),
		candidate(2, Section::Czech, 1785, 1.0),
		...modern(6),
		...old(6),
	];
	Assert::false(in_array(1, ids((new DaySelector)->select($candidates)), true));
});


test('not excluded: turning points, firsts, big sport and words that only look like sport or politics', function () {
	$classifier = TestContainer::create()->getByType(EventClassifier::class);
	foreach ([
		'V Praze byl vyhlášen samostatný československý stát.',
		'V Mnichově byla podepsána mnichovská dohoda.',
		'Byla podepsána mírová smlouva, která ukončila válku.',
		'V německých parlamentních volbách zvítězila nacistická strana NSDAP.',
		'Polský prezident jmenoval Tadeusze Mazowieckeho prvním nekomunistickým premiérem.',
		'První svobodné volby v Československu po čtyřiceti letech.',
		'George H. W. Bush jako první americký prezident navštívil Prahu.',
		'Konaly se první novodobé olympijské hry.',
		'Emil Zátopek vyhrál na olympijských hrách tři zlaté medaile.',
		'Českoslovenští hokejisté se stali mistry světa.',
		'Maxmilián I. založil Katolickou ligu.',
		'Francouzský král Jindřich II. byl těžce raněn během rytířského turnaje.',
		'Baseballista Babe Ruth odehrál svůj poslední zápas.',
		'Gregor Mendel představil na zasedání Přírodovědného spolku výsledky své práce.',
		'Zemský sněm přijal usnesení, že má být na Moravě zrušena robota.',
	] as $text) {
		Assert::null($classifier->excludedReason($text), $text);
	}
});


test('type of an event: premiéra of a work is not a prime minister', function () {
	$classifier = TestContainer::create()->getByType(EventClassifier::class);
	$types = [
		'Prezident Edvard Beneš jmenoval druhou vládu Zdeňka Fierlingera.' => 'vláda',
		'Jan Lucemburský byl korunován českým králem.' => 'korunovace',
		'Světová premiéra opery Djamileh se konala v Paříži.' => 'premiéra díla',
		'Prezident jmenoval premiéra.' => 'vláda',
		'Českoslovenští hokejisté se stali mistry světa.' => 'sport',
		'Bitva u Lipan.' => 'bitva',
		'Angola získala nezávislost na Portugalsku.' => 'nezávislost',
		'Císař Karel I. podepsal abdikační listinu.' => 'abdikace',
		'Washington se stal 42. státem USA.' => 'stát USA',
		'Jugoslávský prezident Josip Broz Tito navštívil Prahu.' => 'návštěva',
	];
	foreach ($types as $text => $type) {
		Assert::same($type, $classifier->type($text), $text);
	}
	Assert::null($classifier->type('Byla založena Univerzita Karlova.'));
});


test('remarkable: ends of dynasties, firsts, discoveries, the examples of a good event', function () {
	$classifier = TestContainer::create()->getByType(EventClassifier::class);
	foreach ([
		'Vymřel rod pánů z Rožmberka (Petrem Vokem v Třeboni).',
		'Španělský dobyvatel ztroskotal u pobřeží Texasu a stal se prvním Evropanem, který vstoupil na texaskou půdu.',
		'Americký oceánograf Robert D. Ballard objevil vrak Titanicu.',
		'Nadporučík Jurij Alexejevič Gagarin jako první pobýval ve vesmíru a obletěl zeměkouli.',
	] as $text) {
		Assert::true($classifier->isRemarkable($text), $text);
	}
	foreach ([
		'Byla objevena planetka Inastronoviny.',
		'Řecký král abdikoval ve prospěch svého nejstaršího syna.',
	] as $text) {
		Assert::false($classifier->isRemarkable($text), $text);
	}
});


test('sensitive: victims, war, disasters, terror – the rest is not', function () {
	$classifier = TestContainer::create()->getByType(SensitivityClassifier::class);
	foreach (['Husité dobyli Mohelnici a povraždili na 700 jejích obyvatel.', 'Japonsko zaútočilo na Pearl Harbor.', 'Zemětřesení v Lisabonu.', 'Atentát na Heydricha.', 'Při požáru zahynulo 286 lidí.'] as $text) {
		Assert::true($classifier->isSensitive($text), $text);
	}
	foreach (['Kryštof Kolumbus přistál na Kubě.', 'Založena Harvardova univerzita.', 'Premiéra hry České nebe.'] as $text) {
		Assert::false($classifier->isSensitive($text), $text);
	}
});


test('invalid rule patterns are refused at start', function () {
	Assert::exception(fn() => new TextRules(['vadné' => '~(~']), InvalidArgumentException::class, "%a%'vadné'%a%");
});


/**
 * @param list<Candidate> $chosen
 * @return list<int>
 */
function sortedIds(array $chosen): array
{
	$ids = ids($chosen);
	sort($ids);
	return $ids;
}


test('ten cards, at least three Czech, the rest by score, newest first', function () {
	$candidates = [];
	foreach (range(1, 10) as $i) {
		$candidates[] = candidate($i, Section::World, 1500 + 30 * $i, 1 - $i / 20);    // world 0.95 … 0.50
	}
	foreach ([[11, 0.3], [12, 0.2], [13, 0.1], [14, 0.05]] as [$id, $score]) {
		$candidates[] = candidate($id, Section::Czech, 1400 + $id, $score);           // Czech scores lower
	}
	$chosen = (new DaySelector)->select($candidates);

	Assert::count(10, $chosen);
	Assert::same([1, 2, 3, 4, 5, 6, 7, 11, 12, 13], sortedIds($chosen)); // 3 best Czech + 7 best world
	$years = array_map(fn(Candidate $c) => $c->year, $chosen);
	$sorted = $years;
	rsort($sorted);
	Assert::same($sorted, $years);
});


test('fewer than three Czech events: all of them, the rest from the world', function () {
	$candidates = [candidate(1, Section::Czech, 1995, 0.1)];
	foreach (range(2, 14) as $i) {
		$candidates[] = candidate($i, Section::World, 1000 + 50 * $i, 1 - $i / 20);
	}
	$chosen = (new DaySelector)->select($candidates);
	Assert::count(10, $chosen);
	Assert::contains(1, ids($chosen));
});


test('any age can be chosen: antiquity next to the present', function () {
	$chosen = (new DaySelector)->select([
		candidate(1, Section::World, -480, 1.0),
		candidate(2, Section::World, 2020, 0.9),
		candidate(3, Section::Czech, 863, 1.0),
		candidate(4, Section::Czech, 2004, 0.9),
	]);
	Assert::same([2, 4, 3, 1], ids($chosen)); // newest first: 2020, 2004, 863, -480
});


test('a higher score wins over century variety', function () {
	$candidates = [
		candidate(1, Section::Czech, 1618, 0.9),
		candidate(2, Section::Czech, 1645, 0.8),  // 17th century again, but better
		candidate(3, Section::Czech, 1668, 0.7),
		candidate(4, Section::Czech, 1424, 0.1),  // a new century, but a low score
		candidate(5, Section::World, 1300, 0.05), // a new century, but the lowest score
	];
	foreach (range(6, 12) as $i) {
		$candidates[] = candidate($i, Section::World, 1700 + $i, 1 - $i / 20);
	}
	Assert::same([1, 2, 3, 6, 7, 8, 9, 10, 11, 12], sortedIds((new DaySelector)->select($candidates)));
});


test('at an equal score a century not yet chosen wins', function () {
	$candidates = [
		candidate(1, Section::Czech, 1618, 0.5),
		candidate(2, Section::Czech, 1645, 0.5),
		candidate(3, Section::Czech, 1424, 0.5), // beats 1645 for the second Czech place: new century
		candidate(4, Section::Czech, 1660, 0.5),
	];
	foreach ([5 => 1669, 6 => 1661, 7 => 1492, 8 => 1305, 9 => 1210, 10 => 1110, 11 => 1050, 12 => 1699, 13 => 1480, 14 => 966] as $id => $year) {
		$candidates[] = candidate($id, Section::World, $year, 0.4);
	}
	// Czech 1618, 1424, 1645, then the fourth Czech (0.5) outscores the world (0.4);
	// world: first the new centuries 14th, 13th, 12th, 11th, 10th, then by id
	Assert::same([1, 2, 3, 4, 5, 8, 9, 10, 11, 14], sortedIds((new DaySelector)->select($candidates)));

	$crowded = (new DaySelector)->select(array_map(fn(int $i) => candidate($i, $i <= 3 ? Section::Czech : Section::World, 1900 + $i, 0.5), range(1, 12)));
	Assert::count(10, $crowded); // all from one century: allowed when unavoidable
});


test('dull events only fill places nobody else can take', function () {
	$candidates = [
		candidate(1, Section::Czech, 1935, 1.0, 'otevření stavby'),
		candidate(2, Section::Czech, 1918, 0.2),
		candidate(3, Section::Czech, 1453, 0.1),
		candidate(4, Section::Czech, 1620, 0.05),
		candidate(5, Section::World, 2007, 1.0, 'vydání softwaru'),
	];
	foreach (range(6, 10) as $i) {
		$candidates[] = candidate($i, Section::World, 1500 + 40 * $i, 0.3);
	}
	// 8 interesting events: both dull ones fill the last two places
	Assert::same([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], sortedIds((new DaySelector)->select($candidates)));

	$candidates[] = candidate(11, Section::World, 1111, 0.01);
	$candidates[] = candidate(12, Section::World, 1212, 0.01);
	$chosen = (new DaySelector)->select($candidates);
	Assert::false(in_array(1, ids($chosen), true));
	Assert::false(in_array(5, ids($chosen), true));
});


/**
 * @return list<Candidate> world events from 1900 on, stronger than anything in the tests below
 */
function modern(int $count, int $firstId = 100): array
{
	return array_map(fn(int $i) => candidate($firstId + $i, Section::World, 1900 + $i, 1 - $i / 100), range(0, $count - 1));
}


/**
 * @return list<Candidate> weak world events before 1800
 */
function old(int $count, int $firstId = 200): array
{
	return array_map(fn(int $i) => candidate($firstId + $i, Section::World, 1000 + $i, 0.1), range(0, $count - 1));
}


test('quotas: four events before 1800 despite low scores, at most four from 1900 on', function () {
	$candidates = [...modern(12), ...old(6), candidate(50, Section::World, 1850, 0.5), candidate(51, Section::World, 1860, 0.4), candidate(52, Section::World, 1870, 0.3)];
	$chosen = (new DaySelector)->select($candidates);
	Assert::count(10, $chosen);
	Assert::count(4, array_filter($chosen, fn(Candidate $c) => $c->isModern()));
	Assert::count(4, array_filter($chosen, fn(Candidate $c) => $c->isBefore1800()));
});


test('few old events: all of them, the rest over the modern limit', function () {
	$chosen = (new DaySelector)->select([...modern(12), ...old(2)]);
	Assert::count(10, $chosen);
	Assert::count(2, array_filter($chosen, fn(Candidate $c) => $c->isBefore1800()));
	Assert::count(8, array_filter($chosen, fn(Candidate $c) => $c->isModern()));
});


test('always ten: first over the modern limit, excluded events and duplicates only at the very end', function () {
	$candidates = [
		...modern(6),
		...old(2),
		candidate(1, Section::World, 1950, 1.0, excluded: 'vláda'),
		candidate(2, Section::World, 1960, 0.9, excluded: 'menší sport'),
		candidate(3, Section::World, 1500, 0.8, excluded: 'volby'),
	];
	// 8 good events, the two places left go to the strongest of the excluded
	Assert::same([1, 2, 100, 101, 102, 103, 104, 105, 200, 201], sortedIds((new DaySelector)->select($candidates)));

	$enough = [...$candidates, ...modern(4, 300)];
	Assert::same([], array_values(array_intersect([1, 2, 3], ids((new DaySelector)->select($enough)))));
});


test('at the very end the least bad go first: dull, then duplicates of a type, then excluded', function () {
	$candidates = [
		...modern(7),
		candidate(1, Section::World, 1950, 1.0, excluded: 'vláda'),
		candidate(2, Section::World, 1951, 0.2, type: 'bitva'),
		candidate(3, Section::World, 1952, 0.1, type: 'bitva'),
		candidate(4, Section::World, 1953, 0.05, boring: 'otevření stavby'),
	];
	// good: 7 + the first battle; then dull (4), then the second battle (3); the excluded stays out
	Assert::same([2, 3, 4, 100, 101, 102, 103, 104, 105, 106], sortedIds((new DaySelector)->select($candidates)));
});


test('no two events of a type: only the stronger one, when there is enough else', function () {
	$candidates = [
		candidate(1, Section::World, 1600, 0.9, type: 'korunovace'),
		candidate(2, Section::World, 1650, 0.8, type: 'korunovace'),
		candidate(3, Section::World, 1700, 0.7, type: 'korunovace'),
		...old(12),
	];
	$chosen = ids((new DaySelector)->select($candidates));
	Assert::contains(1, $chosen);
	Assert::false(in_array(2, $chosen, true));
	Assert::false(in_array(3, $chosen, true));
});


test('Czech quota prefers events before 1800, they count towards both quotas', function () {
	$candidates = [
		candidate(1, Section::Czech, 1950, 1.0),
		candidate(2, Section::Czech, 1960, 0.9),
		candidate(3, Section::Czech, 1970, 0.8),
		candidate(4, Section::Czech, 1611, 0.35),
		candidate(5, Section::Czech, 1458, 0.34),
		candidate(6, Section::Czech, 1415, 0.33),
		...modern(6),
		...old(1),
	];
	$chosen = ids((new DaySelector)->select($candidates));
	foreach ([4, 5, 6, 200] as $id) {
		Assert::contains($id, $chosen); // the three old Czech + one old world make four before 1800
	}
});


test('Czech quota prefers only old events with at least a modest score, weaker ones compete like any other', function () {
	$candidates = [
		candidate(1, Section::Czech, 1950, 1.0),
		candidate(2, Section::Czech, 1960, 0.9),
		candidate(3, Section::Czech, 1970, 0.8),
		candidate(4, Section::Czech, 1731, 0.25), // the general guild patent: three languages
		candidate(5, Section::Czech, 1611, DaySelector::MinCzechOldScore),
		...modern(2),
		...array_map(fn(int $i) => candidate(200 + $i, Section::World, 1500 + $i, 0.5), range(0, 5)),
	];
	$chosen = ids((new DaySelector)->select($candidates));
	Assert::contains(5, $chosen);
	Assert::false(in_array(4, $chosen, true)); // old world events with higher scores take the places
});


test('Czech quota wins over the modern limit', function () {
	$candidates = [
		...modern(4),
		candidate(1, Section::Czech, 1950, 0.1),
		candidate(2, Section::Czech, 1960, 0.1),
		candidate(3, Section::Czech, 1970, 0.1),
		...old(8),
	];
	$chosen = (new DaySelector)->select($candidates);
	Assert::same(3, count(array_filter($chosen, fn(Candidate $c) => $c->section === Section::Czech)));
	Assert::count(10, $chosen);
});


test('Czech quota does not bring excluded events back', function () {
	$candidates = [
		candidate(1, Section::Czech, 2006, 1.0, excluded: 'vláda'),
		candidate(2, Section::Czech, 2011, 0.9, excluded: 'menší sport'),
		candidate(3, Section::Czech, 1965, 0.5),
		...old(6),
		...modern(4),
	];
	$chosen = ids((new DaySelector)->select($candidates));
	Assert::contains(3, $chosen);
	Assert::false(in_array(1, $chosen, true));
	Assert::false(in_array(2, $chosen, true));
});


test('forced events stay even when excluded, and count towards the quotas', function () {
	$candidates = [
		candidate(1, Section::Czech, 1990, 0.1, excluded: 'volby'),
		...modern(8),
		...old(8),
	];
	$chosen = (new DaySelector)->select($candidates, [1]);
	Assert::same(1, $chosen[0]->id);
	Assert::count(4, array_filter($chosen, fn(Candidate $c) => $c->isModern())); // the forced one and three more
});


test('a remarkable event beats a stronger one nearby, not everything', function () {
	$pool = [candidate(1, Section::World, 1850, 0.5, remarkable: true), candidate(2, Section::World, 1860, 0.7)];
	foreach (range(3, 11) as $i) {
		$pool[] = candidate($i, Section::World, 1700 + $i, 1.0);
	}
	$chosen = ids((new DaySelector)->select($pool)); // eleven events, one place for the two of them
	Assert::contains(1, $chosen);
	Assert::false(in_array(2, $chosen, true));

	$weak = [candidate(1, Section::World, 1850, 0.1, remarkable: true), ...array_slice($pool, 1)];
	Assert::false(in_array(1, ids((new DaySelector)->select($weak)), true));
});


test('the same input gives the same ten, in any order', function () {
	$candidates = array_map(fn(int $i) => candidate($i, $i % 3 === 0 ? Section::Czech : Section::World, 1000 + 37 * $i, 0.5), range(1, 30));
	$reversed = array_reverse($candidates);
	Assert::same(ids((new DaySelector)->select($candidates)), ids((new DaySelector)->select($reversed)));
});


test('century of years before Christ', function () {
	Assert::same([1, 20, 21, -1, -5], array_map(fn(int $y) => candidate(1, Section::World, $y, 0)->century(), [1, 2000, 2001, -1, -450]));
});
