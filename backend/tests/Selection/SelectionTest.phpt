<?php

/**
 * Scoring, dull-event filter, sensitivity and the selection of five cards (pure logic).
 */

declare(strict_types=1);

use App\Model\Events\Section;
use App\Model\Selection\BoringFilter;
use App\Model\Selection\Candidate;
use App\Model\Selection\DaySelector;
use App\Model\Selection\EventScorer;
use App\Model\Selection\SensitivityClassifier;
use App\Model\Selection\TextRules;
use Tester\Assert;
use Tests\Support\TestContainer;

require __DIR__ . '/../bootstrap.php';


function candidate(int $id, Section $section, int $year, float $score, ?string $boring = null): Candidate
{
	return new Candidate($id, $section, $year, $score, $boring);
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
		'sportovní výsledek' => 'Sparta porazila Slavii v utkání ligy 3:1.',
		'skóre zápasu' => 'Finále skončilo výsledkem 2:0.',
		'otevření obchodu' => 'V Brně byl otevřen první supermarket.',
		'otevření stavby' => 'Otevřen Švehlův most přes Lužnici v Táboře.',
		'vydání softwaru' => 'Byla vydána nová verze komunikačního programu ICQ pod názvem ICQ 6.',
		'drobná nehoda' => 'Při srážce dvou tramvají se zranilo 12 lidí.',
	];
	foreach ($dull as $rule => $text) {
		Assert::same($rule, $filter->reason($text), $text);
	}
	foreach ([
		'V Praze byl vyhlášen samostatný československý stát.',
		'Ve 23:59 končí existence Československa.',
		'Při srážce auta a dvou tramvají zahynul lékař Josef Rejsek.', // a death is not a minor accident
		'Začalo vysílat Radio Proglas.',
		'Konaly se první novodobé olympijské hry.',
	] as $text) {
		Assert::null($filter->reason($text), $text);
	}
	// the case that removed the sitelinks exemption: the primary article was the backdrop "Švédsko"
	Assert::same('skóre zápasu', $filter->reason('Českoslovenští házenkáři se stali ve Švédsku mistry světa, když ve finále MS porazili Dánsko 14:11.'));
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


test('five cards, at least two Czech, chronological', function () {
	$chosen = (new DaySelector)->select([
		candidate(1, Section::World, 1886, 1.0),
		candidate(2, Section::World, 1492, 0.9),
		candidate(3, Section::World, 1636, 0.8),
		candidate(4, Section::World, 1922, 0.7),
		candidate(5, Section::World, 1969, 0.6),
		candidate(6, Section::Czech, 1918, 0.3),
		candidate(7, Section::Czech, 1453, 0.2),
	]);
	Assert::same([7, 2, 3, 1, 6], ids($chosen));
});


test('fewer than two Czech events: all of them, the rest from the world', function () {
	$chosen = (new DaySelector)->select([
		candidate(1, Section::Czech, 1995, 0.1),
		candidate(2, Section::World, 1792, 1.0),
		candidate(3, Section::World, 1864, 0.9),
		candidate(4, Section::World, 1941, 0.8),
		candidate(5, Section::World, 1869, 0.7),
		candidate(6, Section::World, 1980, 0.6),
	]);
	Assert::count(5, $chosen);
	Assert::contains(1, ids($chosen));
});


test('a higher score wins over century variety', function () {
	$chosen = (new DaySelector)->select([
		candidate(1, Section::Czech, 1918, 1.0),
		candidate(2, Section::Czech, 1945, 0.9),  // 20th century again, but the better Czech event
		candidate(3, Section::Czech, 1424, 0.5),
		candidate(4, Section::World, 1941, 1.0),  // 20th again, best world event
		candidate(5, Section::World, 1886, 0.8),
		candidate(6, Section::World, 1636, 0.7),
		candidate(7, Section::World, 312, 0.6),
	]);
	Assert::same([6, 5, 1, 4, 2], ids($chosen));
});


test('at an equal score a century not yet chosen wins', function () {
	$chosen = (new DaySelector)->select([
		candidate(1, Section::Czech, 1918, 0.5),
		candidate(2, Section::Czech, 1945, 0.5),  // same score, but the 20th century is already chosen
		candidate(3, Section::Czech, 1424, 0.5),
		candidate(4, Section::World, 1969, 0.4),
		candidate(5, Section::World, 1961, 0.4),
		candidate(6, Section::World, 1492, 0.4),
		candidate(7, Section::World, 1805, 0.4),
	]);
	// Czech: 1918 (lowest id), then 1424 over 1945 (new century); the remaining Czech 1945 still
	// outscores the world (0.5 > 0.4); among equal world events 1805 is the only new century
	Assert::same([3, 7, 1, 2, 4], ids($chosen));

	$crowded = (new DaySelector)->select(array_map(fn(int $i) => candidate($i, $i <= 2 ? Section::Czech : Section::World, 1900 + $i, 0.5), range(1, 6)));
	Assert::count(5, $crowded); // all from one century: allowed when unavoidable
});


test('dull events only fill places nobody else can take', function () {
	$chosen = (new DaySelector)->select([
		candidate(1, Section::Czech, 1935, 1.0, 'otevření stavby'),
		candidate(2, Section::Czech, 1918, 0.2),
		candidate(3, Section::Czech, 1453, 0.1),
		candidate(4, Section::World, 2007, 1.0, 'vydání softwaru'),
		candidate(5, Section::World, 1492, 0.3),
		candidate(6, Section::World, 1636, 0.2),
	]);
	Assert::same([3, 5, 6, 2, 4], ids($chosen)); // the two dull ones tie on score; 2007 adds a new century
});


test('the same input gives the same five, in any order', function () {
	$candidates = array_map(fn(int $i) => candidate($i, $i % 3 === 0 ? Section::Czech : Section::World, 1000 + 37 * $i, 0.5), range(1, 20));
	$reversed = array_reverse($candidates);
	Assert::same(ids((new DaySelector)->select($candidates)), ids((new DaySelector)->select($reversed)));
});


test('century of years before Christ', function () {
	Assert::same([1, 20, 21, -1, -5], array_map(fn(int $y) => candidate(1, Section::World, $y, 0)->century(), [1, 2000, 2001, -1, -450]));
});
