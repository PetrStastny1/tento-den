<?php

/**
 * Import of one day into the test database: idempotence, edits, review, missing events,
 * outages and the per-day lock.
 */

declare(strict_types=1);

use App\Core\Clock\Clock;
use App\Model\Events\EventRepository;
use App\Model\Events\EventStatus;
use App\Model\Events\EventSummary;
use App\Model\Events\MonthDay;
use App\Model\Import\ArticleInfoSource;
use App\Model\Import\DayImportService;
use App\Model\Import\DayPageSource;
use App\Model\Import\ImportFailedException;
use App\Model\Import\ImportLockRepository;
use Nette\Database\Explorer;
use Nette\DI\Container;
use Tester\Assert;
use Tests\Support\FakeArticleInfoSource;
use Tests\Support\FakeClock;
use Tests\Support\FixtureDayPageSource;
use Tests\Support\TestContainer;

require __DIR__ . '/../bootstrap.php';


final class ImportFixture
{
	public readonly Container $container;
	public readonly FixtureDayPageSource $pages;
	public readonly FakeClock $clock;


	public function __construct()
	{
		$this->container = TestContainer::createWithFreshDatabase();
		$this->pages = new FixtureDayPageSource;
		$this->clock = new FakeClock;
		TestContainer::replace($this->container, DayPageSource::class, $this->pages);
		TestContainer::replace($this->container, ArticleInfoSource::class, new FakeArticleInfoSource(['Husité' => 'Husitství']));
		TestContainer::replace($this->container, Clock::class, $this->clock);
	}


	public function import(string $day): App\Model\Import\DayImportResult
	{
		return $this->container->getByType(DayImportService::class)->import(MonthDay::fromString($day));
	}


	/**
	 * @return list<EventSummary>
	 */
	public function events(string $day, ?EventStatus $status = null): array
	{
		$events = $this->container->getByType(EventRepository::class)->findByDay(MonthDay::fromString($day));
		return array_values(array_filter($events, fn(EventSummary $e): bool => $status === null || $e->status === $status));
	}


	/**
	 * @return array<string, mixed>
	 */
	public function dayStatus(string $day): array
	{
		[$month, $dayOfMonth] = array_map('intval', explode('-', $day));
		$row = $this->container->getByType(Explorer::class)->fetch('SELECT * FROM import_days WHERE month = ? AND day = ?', $month, $dayOfMonth);
		return $row === null ? [] : (array) $row;
	}


	public function countRows(string $table): int
	{
		return $this->container->getByType(Explorer::class)->table($table)->count('*');
	}


	/**
	 * @return array<int, string> id => text
	 */
	public function snapshot(string $day): array
	{
		$snapshot = [];
		foreach ($this->events($day) as $event) {
			$snapshot[$event->id] = $event->status->value . '|' . $event->sourceRevisionId . '|' . $event->text;
		}
		return $snapshot;
	}
}


test('imports Czech and world events with revision and primary article', function () {
	$fixture = new ImportFixture;
	$fixture->pages->serve('10-28', 'day-page-10-28.html', 26193496);

	$result = $fixture->import('10-28');

	Assert::same([15, 16, 31], [$result->czechCount, $result->worldCount, $result->inserted]);
	$events = $fixture->events('10-28');
	Assert::count(31, $events);
	$state = array_values(array_filter($events, fn($e) => str_contains($e->text, 'samostatný československý stát')))[0];
	Assert::same([1918, 'cz', '28. říjen', 26193496, 'Vznik Československa'], [$state->year, $state->section->value, $state->sourceTitle, $state->sourceRevisionId, $state->primaryTitle]);
	$hussites = array_values(array_filter($events, fn($e) => str_starts_with($e->text, 'Husité')))[0];
	Assert::same('Husitství', $hussites->primaryTitle); // redirect resolved
	Assert::same(['status' => 'ok', 'source_revision_id' => 26193496, 'events_count' => 31], array_intersect_key($fixture->dayStatus('10-28'), array_flip(['status', 'source_revision_id', 'events_count'])));
});


test('importing the same revision again changes nothing', function () {
	$fixture = new ImportFixture;
	$fixture->pages->serve('10-28', 'day-page-10-28.html', 26193496);
	$fixture->import('10-28');
	$before = $fixture->snapshot('10-28');
	$links = $fixture->countRows('event_articles');

	$result = $fixture->import('10-28');

	Assert::same([0, 0, 31, 0, 0], [$result->inserted, $result->updated, $result->unchanged, $result->missing, $result->review]);
	Assert::same($before, $fixture->snapshot('10-28'));
	Assert::same($links, $fixture->countRows('event_articles'));
});


test('a reworded sentence keeps its id, a removed event becomes missing and can come back', function () {
	$fixture = new ImportFixture;
	$fixture->pages->serve('11-17', 'history/day-page-11-17-rev23525658.html', 23525658);
	$fixture->import('11-17');
	$academy = array_values(array_filter($fixture->events('11-17'), fn($e) => $e->year === 1952))[0];
	$funeral = array_values(array_filter($fixture->events('11-17'), fn($e) => $e->year === 1939 && str_contains($e->text, 'pohřbu')))[0];

	$fixture->pages->serve('11-17', 'history/day-page-11-17-rev23526886.html', 23526886);
	$result = $fixture->import('11-17');

	$academyNow = array_values(array_filter($fixture->events('11-17'), fn($e) => $e->id === $academy->id))[0];
	Assert::same('Založena Československá akademie věd, zrušeny všechny dosavadní vědecké instituce a učené společnosti.', $academyNow->text);
	Assert::same([EventStatus::Active, 23526886], [$academyNow->status, $academyNow->sourceRevisionId]);
	Assert::same(1, $result->missing);
	Assert::same([$funeral->id], array_map(fn($e) => $e->id, $fixture->events('11-17', EventStatus::Missing)));

	// the editor reverts: the vanished event is active again under its old id
	$fixture->pages->serve('11-17', 'history/day-page-11-17-rev23525658.html', 23525658);
	$fixture->import('11-17');
	$funeralNow = array_values(array_filter($fixture->events('11-17'), fn($e) => $e->id === $funeral->id))[0];
	Assert::same(EventStatus::Active, $funeralNow->status);
});


test('unclear edits are marked for review exactly once, however often the revision is imported', function () {
	$fixture = new ImportFixture;
	$fixture->pages->serve('01-01', 'history/day-page-01-01-rev24784971.html', 24784971);
	$fixture->import('01-01');
	$eventsBefore = $fixture->countRows('events');

	// vandalism: "Marie Terezie vydala nový trestní zákoník…" → "Marie Terezie si prdla…"
	$fixture->pages->serve('01-01', 'history/day-page-01-01-rev24807572.html', 24807572);
	$first = $fixture->import('01-01');
	$review = $fixture->events('01-01', EventStatus::NeedsReview);
	$rowsAfterFirst = $fixture->countRows('events');

	Assert::same(1, $first->review);
	Assert::count(2, $review); // the original event and the vandalized text, both kept out of selection
	Assert::same($eventsBefore + 1, $rowsAfterFirst);
	Assert::same([], $fixture->events('01-01', EventStatus::Missing));

	foreach ([1, 2] as $attempt) {
		$again = $fixture->import('01-01');
		Assert::same([0, 0, 0], [$again->inserted, $again->review, $again->missing], "reimport $attempt");
		Assert::same($rowsAfterFirst, $fixture->countRows('events'), "reimport $attempt");
		Assert::same(
			array_map(fn($e) => [$e->id, $e->reviewNote], $review),
			array_map(fn($e) => [$e->id, $e->reviewNote], $fixture->events('01-01', EventStatus::NeedsReview)),
			"reimport $attempt",
		);
	}
});


test('an outage keeps stored events and the last success, and records the error', function () {
	$fixture = new ImportFixture;
	$fixture->pages->serve('10-28', 'day-page-10-28.html', 26193496);
	$fixture->import('10-28');
	$before = $fixture->snapshot('10-28');
	$fixture->clock->advance('+1 day');

	$fixture->pages->fail('10-28');
	Assert::exception(fn() => $fixture->import('10-28'), ImportFailedException::class, '%a%Wikipedia is down%a%');

	Assert::same($before, $fixture->snapshot('10-28'));
	$status = $fixture->dayStatus('10-28');
	Assert::same(['error', 26193496, 31], [$status['status'], $status['source_revision_id'], $status['events_count']]);
	Assert::same('2026-10-29 12:00:00', $status['last_success_at']->format('Y-m-d H:i:s'));
	Assert::same('2026-10-30 12:00:00', $status['last_attempt_at']->format('Y-m-d H:i:s'));
});


test('a page the parser does not fully understand changes nothing', function () {
	$fixture = new ImportFixture;
	$fixture->pages->serve('10-28', 'day-page-10-28.html', 26193496);
	$fixture->import('10-28');
	$before = $fixture->snapshot('10-28');

	// one unparseable item among otherwise valid ones must not mark the rest as missing
	$html = (string) file_get_contents(FixtureDayPageSource::Dir . '/day-page-10-28.html');
	$fixture->pages->serveHtml('10-28', str_replace('<ul id="mwDg">', '<ul id="mwDg"><li>Bez roku</li>', $html), 26193497);
	Assert::exception(fn() => $fixture->import('10-28'), ImportFailedException::class, '%a%Položka bez roku%a%');

	Assert::same($before, $fixture->snapshot('10-28'));
	Assert::same('error', $fixture->dayStatus('10-28')['status']);
});


test('a page that suddenly lost most events is treated as broken', function () {
	$fixture = new ImportFixture;
	$fixture->pages->serve('10-28', 'day-page-10-28.html', 26193496);
	$fixture->import('10-28');
	$before = $fixture->snapshot('10-28');

	$fixture->pages->serve('10-28', 'day-page-02-29.html', 26193497); // 6 events instead of 31
	Assert::exception(fn() => $fixture->import('10-28'), ImportFailedException::class, '%a%Podezřelý úbytek%a%');
	Assert::same($before, $fixture->snapshot('10-28'));
});


test('a day being imported by another process is not touched', function () {
	$fixture = new ImportFixture;
	$fixture->pages->serve('10-28', 'day-page-10-28.html', 26193496);
	$otherProcess = TestContainer::create()->getByType(ImportLockRepository::class); // own DB connection
	Assert::true($otherProcess->acquire(MonthDay::fromString('10-28')));

	Assert::exception(fn() => $fixture->import('10-28'), ImportFailedException::class, '%a%už běží%a%');
	Assert::same([], $fixture->events('10-28'));
	Assert::same([], $fixture->dayStatus('10-28'));

	$otherProcess->release(MonthDay::fromString('10-28'));
	Assert::same(31, $fixture->import('10-28')->inserted);
});
