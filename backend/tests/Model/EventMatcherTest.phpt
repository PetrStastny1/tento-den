<?php

/**
 * Pairing of parsed events with stored ones (pure logic, no database).
 */

declare(strict_types=1);

use App\Model\Events\ArticleInfo;
use App\Model\Events\DayPageParser;
use App\Model\Events\EventMatch;
use App\Model\Events\EventMatcher;
use App\Model\Events\EventStatus;
use App\Model\Events\EventText;
use App\Model\Events\IncomingEvent;
use App\Model\Events\LinkFilter;
use App\Model\Events\MatchPlan;
use App\Model\Events\ParsedEvent;
use App\Model\Events\PrimaryArticleSelector;
use App\Model\Events\ReviewCase;
use App\Model\Events\Section;
use App\Model\Events\StoredEvent;
use Tester\Assert;
use Tests\Support\FixtureDayPageSource;

require __DIR__ . '/../bootstrap.php';


function incoming(int $year, string $text, ?string $primary = null, Section $section = Section::Czech): IncomingEvent
{
	$article = $primary === null ? null : new ArticleInfo(crc32($primary), $primary, 1, null);
	return new IncomingEvent(new ParsedEvent($section, $year, $text, 1, []), $article ? [$article] : [], $article);
}


function stored(int $id, int $year, string $text, ?string $primary = null, EventStatus $status = EventStatus::Active): StoredEvent
{
	return new StoredEvent($id, Section::Czech, $year, $text, EventText::hash($text), $status, $primary === null ? null : crc32($primary));
}


test('identical text is the same event, whitespace and case do not matter', function () {
	$plan = (new EventMatcher)->match(
		[stored(1, 1918, 'Vznik Československa.')],
		[incoming(1918, "vznik\u{00A0}Československa.")],
	);
	Assert::count(1, $plan->matches);
	Assert::true($plan->matches[0]->exact);
	Assert::same([[], [], []], [$plan->inserts, $plan->reviews, $plan->missingIds]);
});


test('a reworded sentence keeps the id of the stored event', function () {
	$plan = (new EventMatcher)->match(
		[stored(7, 2000, 'České republice vzniklo 14 nových krajů.', 'Kraje v Česku')],
		[incoming(2000, 'V České republice vzniklo 14 nových samosprávných krajů.', 'Kraje v Česku')],
	);
	Assert::count(1, $plan->matches);
	Assert::same(7, $plan->matches[0]->stored->id);
	Assert::false($plan->matches[0]->exact);
});


test('a moderately similar sentence is matched only with the same primary article', function () {
	$old = 'Byla vyhlášena CHKO Brdy.';
	$new = 'Byla vyhlášena Chráněná krajinná oblast Brdy.';
	Assert::count(1, (new EventMatcher)->match([stored(1, 2016, $old, 'CHKO Brdy')], [incoming(2016, $new, 'CHKO Brdy')])->matches);

	$plan = (new EventMatcher)->match([stored(1, 2016, $old, 'CHKO Brdy')], [incoming(2016, $new, 'Brdy')]);
	Assert::same([], $plan->matches);
	Assert::same([1 => 0.69], $plan->reviews[0]->candidates);
});


test('the same text in another year or section is a different event', function () {
	$plan = (new EventMatcher)->match(
		[stored(1, 1918, 'Vznik Československa.')],
		[incoming(1919, 'Vznik Československa.'), incoming(1918, 'Vznik Československa.', null, Section::World)],
	);
	Assert::same([], $plan->matches);
	Assert::count(2, $plan->inserts);
	Assert::same([1], $plan->missingIds);
});


test('an unclear edit is not guessed: new event and its candidate go to review', function () {
	$plan = (new EventMatcher)->match(
		[stored(5, 1923, 'První let nově vzniklých Československých aerolinií se uskutečnil v relaci Praha–Bratislava.', 'ČSA')],
		[incoming(1923, 'Československé státní aerolinie uskutečnily svůj první let – symbolický oblet pražského letiště.', 'ČSA')],
	);
	Assert::same([], $plan->matches);
	Assert::same([], $plan->missingIds);
	Assert::count(1, $plan->reviews);
	Assert::same([5], array_keys($plan->reviews[0]->candidates));
});


test('two similar candidates are ambiguous, so nothing is matched', function () {
	$plan = (new EventMatcher)->match(
		[stored(1, 1989, 'Studenti vyšli do ulic Prahy.'), stored(2, 1989, 'Studenti vyšli do ulic Brna.')],
		[incoming(1989, 'Studenti vyšli do ulic Prahy i Brna.')],
	);
	Assert::same([], $plan->matches);
	Assert::same([1, 2], array_keys($plan->reviews[0]->candidates));
});


test('a vanished event becomes missing and comes back when it reappears', function () {
	$plan = (new EventMatcher)->match([stored(3, 1939, 'Zavřeny vysoké školy.')], []);
	Assert::same([3], $plan->missingIds);

	$plan = (new EventMatcher)->match([stored(3, 1939, 'Zavřeny vysoké školy.', null, EventStatus::Missing)], [incoming(1939, 'Zavřeny vysoké školy.')]);
	Assert::same(3, $plan->matches[0]->stored->id);
	Assert::same([], $plan->missingIds); // already missing events are not reported again
});


test('events in review are matched only exactly, which keeps reimports idempotent', function () {
	$plan = (new EventMatcher)->match(
		[
			stored(1, 1770, 'Marie Terezie vydala trestní zákoník.', null, EventStatus::NeedsReview),
			stored(2, 1770, 'Marie Terezie byla zabita.', null, EventStatus::NeedsReview),
		],
		[incoming(1770, 'Marie Terezie byla zabita.')],
	);
	Assert::same(2, $plan->matches[0]->stored->id);
	Assert::same([[], [], []], [$plan->inserts, $plan->reviews, $plan->missingIds]); // event 1 stays in review
});


/**
 * Real edits between two revisions of a day page (fixtures/wikimedia/history), see docs/rozhodnuti.md.
 */
function planBetweenRevisions(string $day, int $oldRevision, int $newRevision): MatchPlan
{
	$parser = new DayPageParser(new LinkFilter);
	$selector = new PrimaryArticleSelector(['Praha', 'Česko', 'Československo', 'Spojené státy americké']);
	$toIncoming = function (ParsedEvent $event) use ($selector): IncomingEvent {
		$articles = array_map(fn(string $title) => new ArticleInfo(crc32($title), $title, 1, null), $event->linkTitles);
		return new IncomingEvent($event, $articles, $selector->select($articles));
	};
	$read = fn(int $revision) => $parser->parse((string) file_get_contents(FixtureDayPageSource::Dir . "/history/day-page-$day-rev$revision.html"));

	$stored = [];
	foreach (array_map($toIncoming, $read($oldRevision)) as $i => $event) {
		$stored[] = new StoredEvent($i + 1, $event->parsed->section, $event->parsed->year, $event->parsed->text, $event->textHash, EventStatus::Active, $event->primaryArticle?->pageId);
	}
	return (new EventMatcher)->match($stored, array_map($toIncoming, $read($newRevision)));
}


/**
 * @param array<EventMatch|ReviewCase|IncomingEvent> $items
 * @return list<int> years of the given part of the plan
 */
function years(array $items): array
{
	$years = array_map(fn($item) => ($item instanceof IncomingEvent ? $item : $item->incoming)->parsed->year, array_values($items));
	sort($years);
	return $years;
}


test('real edits: 17. listopad, rewording of several Czech events', function () {
	$plan = planBetweenRevisions('11-17', 23525658, 23526886);
	Assert::same([1945, 1952, 1968, 1989, 1989, 1989, 1999, 2014], years(array_filter($plan->matches, fn($m) => !$m->exact)));
	Assert::same([], $plan->reviews);
	Assert::same([1939, 1939], years($plan->inserts)); // one event split into two new ones
	Assert::count(1, $plan->missingIds);
});


test('real edits: 1. leden, rewording and factual corrections', function () {
	$plan = planBetweenRevisions('01-01', 23905020, 23959580);
	Assert::same([1990, 1993, 2000, 2011, 2016], years(array_filter($plan->matches, fn($m) => !$m->exact)));
	Assert::same([1992, 1996], years($plan->reviews));
	Assert::same([1983], years($plan->inserts));
	Assert::count(1, $plan->missingIds); // Havel's speech was removed
});


test('real edits: a changed fact and vandalism go to review, never to active', function () {
	Assert::same([1923], years(planBetweenRevisions('10-28', 23260912, 23272755)->reviews));
	$vandalism = planBetweenRevisions('01-01', 24784971, 24807572);
	Assert::same([1770], years($vandalism->reviews));
	Assert::same([[], []], [$vandalism->inserts, $vandalism->missingIds]);
});
