<?php

/**
 * At most one round anniversary a day, only of a big event, never an excluded one.
 */

declare(strict_types=1);

use App\Model\Anniversaries\AnniversaryConfig;
use App\Model\Anniversaries\AnniversaryExclusions;
use App\Model\Anniversaries\AnniversarySelector;
use App\Model\Events\EventStatus;
use App\Model\Events\MonthDay;
use App\Model\Events\Section;
use App\Model\Selection\Candidate;
use App\Model\Selection\CardData;
use App\Model\Selection\DayAnalysis;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


/**
 * @param list<int> $eventYears
 */
function event(int $id, int $year, ?string $class = null, array $eventYears = [], int $sitelinks = 100, string $text = 'Událost.'): CardData
{
	return new CardData($id, new MonthDay(8, 29), $year, Section::World, $text, EventStatus::Active, '29. srpen', 1, new DateTimeImmutable,
		"Článek $id", 1, $sitelinks, [], $class, $eventYears);
}


/**
 * @param list<CardData> $events
 * @param array<int, float> $scores
 */
function analysis(array $events, array $scores = []): DayAnalysis
{
	$byId = $candidates = [];
	foreach ($events as $event) {
		$byId[$event->id] = $event;
		$candidates[] = new Candidate($event->id, $event->section, $event->year, $scores[$event->id] ?? 0.5, null);
	}
	return new DayAnalysis($candidates, [], $byId);
}


/**
 * @param list<array{day: string, year: int, text: string, reason: string}> $exclusions
 */
function selector(array $exclusions = []): AnniversarySelector
{
	return new AnniversarySelector(new AnniversaryConfig(['Q178561' => 'bitva', 'Q198' => 'válka'], 60), new AnniversaryExclusions($exclusions));
}


test('a big event: primary article is a battle dated in the year of the event, with enough sitelinks', function () {
	$mohacs = event(1, 1526, 'Q178561', [1526], 65);
	$anniversary = selector()->choose(analysis([$mohacs]), [], 2026);
	Assert::same([1, 500, '500 let'], [$anniversary?->eventId, $anniversary?->years, $anniversary?->label()]);
	Assert::contains('bitva (Q178561), datum 1526, 65 jazykových verzí', (string) $anniversary?->reason);
});


test('not big, not shown: no event class, too few sitelinks, or the article is a war of another year', function () {
	Assert::null(selector()->choose(analysis([event(1, 1526)]), [], 2026));
	Assert::null(selector()->choose(analysis([event(1, 1526, 'Q178561', [1526], 59)]), [], 2026));
	// „Thirty Years' War“ (1618) as the primary article of a battle in 1626 – not this event
	Assert::null(selector()->choose(analysis([event(1, 1626, 'Q198', [1618], 124)]), [], 2026));
	Assert::null(selector()->choose(analysis([event(1, 1527, 'Q178561', [1527], 99)]), [], 2026)); // 499 years: not round
});


test('an event commemorated by a state day is big without any other condition, years before Christ count right', function () {
	Assert::same(100, selector()->choose(analysis([event(7, 1918)]), [7], 2018)?->years);
	Assert::same(2100, selector()->choose(analysis([event(3, -75, 'Q178561', [-75])]), [], 2026)?->years);
});


test('at most one a day: the higher anniversary, then the higher score', function () {
	$events = [event(1, 1926, 'Q178561', [1926]), event(2, 1526, 'Q178561', [1526]), event(3, 1776, 'Q198', [1776])];
	Assert::same(2, selector()->choose(analysis($events), [], 2026)?->eventId); // 500 > 250 > 100
	$same = [event(4, 1926, 'Q178561', [1926]), event(5, 1926, 'Q198', [1926])];
	Assert::same(5, selector()->choose(analysis($same, [4 => 0.4, 5 => 0.9]), [], 2026)?->eventId);
});


test('an excluded event is never the anniversary; the next one in order is', function () {
	$events = [event(1, 1526, 'Q178561', [1526], 65, 'V bitvě u Moháče porazili Osmani uherské vojsko.'), event(2, 1926, 'Q178561', [1926])];
	$exclusions = [['day' => '08-29', 'year' => 1526, 'text' => 'bitvě u Moháče', 'reason' => 'test']];
	Assert::same(2, selector($exclusions)->choose(analysis($events), [], 2026)?->eventId);
	Assert::exception(fn() => new AnniversaryExclusions([['day' => '08-29', 'year' => 1526, 'text' => 'krátký']]), InvalidArgumentException::class, '%a%reason%a%');
});
