<?php

/**
 * Parsing of cs.wikipedia day pages (Parsoid HTML), including the irregular formats
 * documented in docs/zdroje.md.
 */

declare(strict_types=1);

use App\Model\Events\DayPageParseException;
use App\Model\Events\DayPageParser;
use App\Model\Events\DayPageParseResult;
use App\Model\Events\DayPageRules;
use App\Model\Events\EventText;
use App\Model\Events\LinkFilter;
use App\Model\Events\MonthDay;
use App\Model\Events\ParsedEvent;
use App\Model\Events\Section;
use Tester\Assert;
use Tests\Support\FixtureDayPageSource;

require __DIR__ . '/../bootstrap.php';


function parser(?DayPageRules $rules = null): DayPageParser
{
	return new DayPageParser(new LinkFilter, $rules ?? new DayPageRules([], []));
}


/**
 * @return list<ParsedEvent>
 */
function parseFixture(string $file): array
{
	return parser()->parse((string) file_get_contents(FixtureDayPageSource::Dir . '/' . $file), new MonthDay(1, 1))->events;
}


function parseHtmlResult(string $events, ?DayPageRules $rules = null, string $day = '06-30'): DayPageParseResult
{
	return parser($rules)->parse(<<<HTML
		<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>
		<section><h2 id="Události">Události</h2>$events</section>
		<section><h2 id="Narození">Narození</h2><ul><li>1900 – Někdo se narodil.</li></ul></section>
		</body></html>
		HTML, MonthDay::fromString($day));
}


/**
 * @return list<ParsedEvent>
 */
function parseHtml(string $events): array
{
	return parseHtmlResult($events)->events;
}


/**
 * @param list<ParsedEvent> $events
 */
function findEvent(array $events, int $year, string $textStart): ParsedEvent
{
	foreach ($events as $event) {
		if ($event->year === $year && str_starts_with($event->text, $textStart)) {
			return $event;
		}
	}
	throw new LogicException("Event $year '$textStart' not found");
}


test('counts of Czech and world events on real pages match docs/zdroje.md', function () {
	$expected = [
		'day-page-10-28.html' => [15, 16],
		'day-page-02-29.html' => [3, 3],      // the poorest day
		'day-page-12-08.html' => [1, 12],     // a single Czech event
		'day-page-08-15.html' => [10, 12],    // years without links
		'day-page-05-22.html' => [4, 70],     // comment in heading, split by centuries
		'day-page-12-31.html' => [8, 14],     // parent with own text and nested items = one event
		'day-page-02-07.html' => [8, 18],     // {{0}} padding, "1984 v dopravě|1984"
	];
	foreach ($expected as $file => [$czech, $world]) {
		$sections = array_count_values(array_map(fn(ParsedEvent $e): string => $e->section->value, parseFixture($file)));
		Assert::same([$czech, $world], [$sections['cz'] ?? 0, $sections['world'] ?? 0], $file);
	}
});


test('standard line: year, text without the dash, links without the year', function () {
	$event = findEvent(parseFixture('day-page-10-28.html'), 1939, 'V');
	Assert::same(Section::Czech, $event->section);
	Assert::same("V\u{00A0}řadě měst protektorátu Čechy a Morava proběhly protiněmecké demonstrace. V\u{00A0}Praze byl při nich smrtelně zraněn medik Jan Opletal a dělník Václav Sedláček.", $event->text);
	Assert::same([
		'Protektorát Čechy a Morava',
		'Protesty proti nacistické okupaci v Protektorátu Čechy a Morava 28. října 1939',
		'Jan Opletal',
		'Václav Sedláček',
	], $event->linkTitles);
});


test('a year with nested items gives one event per item, all in that year', function () {
	$events = parseFixture('day-page-10-28.html');
	$czechStateEvent = findEvent($events, 1918, "V\u{00A0}Praze byl vyhlášen");
	Assert::same(['Praha', 'Vznik Československa', 'První republika'], $czechStateEvent->linkTitles);
	findEvent($events, 1918, 'Vznikla Československá tisková kancelář');
});


test('a parent with own text keeps its nested items in one event', function () {
	$event = findEvent(parseFixture('day-page-12-31.html'), 1992, 've 23:59 končí existence Československa');
	Assert::contains('; zanikají veřejnoprávní instituce Československý rozhlas', $event->text);
	Assert::notContains('.;', $event->text);
	Assert::contains('Československé státní dráhy', $event->linkTitles);
});


test('hidden {{0}} padding does not end up in the year or text', function () {
	$event = findEvent(parseFixture('day-page-02-07.html'), 999, 'Po smrti Boleslava II.');
	Assert::same(['Boleslav II.', 'Boleslav III.'], $event->linkTitles);
});


test('years written without a link are parsed', function () {
	Assert::same(1099, parseFixture('day-page-08-15.html')[0]->year);
});


test('years before Christ are negative', function () {
	$events = parseFixture('history/day-page-01-01-rev24784971.html');
	Assert::same(-45, findEvent($events, -45, "V\u{00A0}Římské říši se začal používat")->year);
	Assert::same(-1, parseHtml('<section><h3>Svět</h3><ul><li>1 př. n. l. – Konec.</li></ul></section>')[0]->year);
});


test('years before Christ written out in words', function () {
	$events = parseHtml('<section><h3>Svět</h3><ul><li>360 před naším letopočtem – Zatmění Měsíce.</li><li>45 před&nbsp;naším letopočtem Kalendář.</li></ul></section>');
	Assert::same([[-360, 'Zatmění Měsíce.'], [-45, 'Kalendář.']], array_map(fn(ParsedEvent $e) => [$e->year, $e->text], $events));
});


test('references, red links, years and date pages are not kept', function () {
	$events = parseFixture('day-page-12-08.html');
	$laurens = findEvent($events, 1792, 'Henry Laurens');
	Assert::notContains('Henry Laurens', $laurens->linkTitles); // red link: the article does not exist
	foreach ($events as $event) {
		foreach ($event->linkTitles as $title) {
			Assert::true((new LinkFilter)->isArticleCandidate($title), $title);
		}
		Assert::notContains('[', $event->text); // no reference markers like [1]
	}
});


test('births and deaths are not imported', function () {
	$events = parseFixture('day-page-02-29-full.html');
	Assert::count(6, $events);
	Assert::count(1, parseHtml('<section><h3>Česko</h3><ul><li>1950 – Událost.</li></ul></section>'));
});


test('empty list items carry nothing and are ignored', function () {
	Assert::count(1, parseHtml('<section><h3>Česko</h3><ul><li> </li><li>1950 – Událost.</li></ul></section>'));
});


test('anything not understood fails the whole page instead of being skipped', function () {
	$cases = [
		'item without a year' => '<section><h3>Česko</h3><ul><li>Tříkrálová sbírka (každoročně od roku 2001)</li></ul></section>',
		'year without text' => '<section><h3>Česko</h3><ul><li>1974 –</li></ul></section>',
		'year 0' => '<section><h3>Svět</h3><ul><li>0 – Nic.</li></ul></section>',
		'unknown subsection' => '<section><h3>Pravidelné události</h3><ul><li>2001 – Sbírka.</li></ul></section>',
		'item outside a subsection' => '<ul><li>2001 – Sbírka.</li></ul><section><h3>Česko</h3></section>',
	];
	foreach ($cases as $case => $html) {
		try {
			parseHtml($html);
			Assert::fail("$case: the page was accepted");
		} catch (DayPageParseException) {
			// expected
		}
	}
	Assert::exception(
		fn() => parser()->parse('<html><body><h2>Narození</h2></body></html>', new MonthDay(1, 1)),
		DayPageParseException::class,
		'Stránka nemá sekci „Události“',
	);
});


const TatraText = 'Automobilka Tatra vyrobila poslední exemplář modelu Tatra 148.';


test('an ignored subsection is skipped by name and reported', function () {
	$result = parseHtmlResult(
		'<section><h3>Pravidelné události</h3><ul><li>Tříkrálová sbírka (každoročně od roku 2001)</li></ul></section>'
		. '<section><h3>Česko</h3><ul><li>1950 – Událost.</li></ul></section>',
		new DayPageRules(['Pravidelné události'], []),
	);
	Assert::count(1, $result->events);
	Assert::same(['ignorovaná podsekce „Pravidelné události“ (1 položek)'], $result->skipped);
});


test('an unknown subsection still fails the day, even when other subsections are ignored', function () {
	Assert::exception(
		fn() => parseHtmlResult('<section><h3>Sport</h3><ul><li>2001 – Výsledek.</li></ul></section>', new DayPageRules(['Pravidelné události'], [])),
		DayPageParseException::class,
		'%a%neznámé podsekci „Sport“%a%',
	);
});


test('a known item without a year is skipped on its day only', function () {
	$rules = new DayPageRules([], [['day' => '12-14', 'fingerprint' => EventText::hash(TatraText)]]);
	$html = '<section><h3>Česko</h3><ul><li>' . TatraText . '</li><li>1950 – Událost.</li></ul></section>';

	$result = parseHtmlResult($html, $rules, '12-14');
	Assert::count(1, $result->events);
	Assert::same(['známá položka bez roku (cz): ' . TatraText], $result->skipped);

	Assert::exception(fn() => parseHtmlResult($html, $rules, '12-15'), DayPageParseException::class, 'Položka bez roku%a%');
});


test('a changed text of a known item fails the day again and prints the new fingerprint', function () {
	$rules = new DayPageRules([], [['day' => '12-14', 'fingerprint' => EventText::hash(TatraText)]]);
	$changed = 'Automobilka Tatra vyrobila úplně poslední exemplář modelu Tatra 148.';
	Assert::exception(
		fn() => parseHtmlResult('<section><h3>Česko</h3><ul><li>' . $changed . '</li></ul></section>', $rules, '12-14'),
		DayPageParseException::class,
		'Položka bez roku (cz, otisk ' . EventText::hash($changed) . '): ' . $changed,
	);
});


test('the configured rules are loaded and invalid fingerprints are refused', function () {
	$rules = Tests\Support\TestContainer::create()->getByType(DayPageRules::class);
	Assert::true($rules->isIgnoredSubsection('Pravidelné události'));
	Assert::true($rules->isIgnoredSubsection('Hospodářství a ekologie'));
	Assert::false($rules->isIgnoredSubsection('Česko'));
	Assert::true($rules->isKnownItemWithoutYear(MonthDay::fromString('06-30'), 'Jedno z dat, kdy je v případě potřeby zavedena přestupná sekunda.'));
	Assert::exception(fn() => new DayPageRules([], [['day' => '06-30', 'fingerprint' => 'abc']]), InvalidArgumentException::class);
});
