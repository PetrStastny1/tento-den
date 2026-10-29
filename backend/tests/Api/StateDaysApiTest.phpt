<?php

/**
 * State holidays and significant days in GET /api/day: an event commemorated by a state day,
 * a holiday card without an event, movable holidays by year and a fragment that matches nothing.
 */

declare(strict_types=1);

use App\Core\Clock\Clock;
use App\Model\Events\MonthDay;
use App\Model\Import\ArticleInfoSource;
use App\Model\Import\DayImportService;
use App\Model\Import\DayPageSource;
use App\Model\StateDays\ArticleSummarySource;
use App\Model\StateDays\StateDayArticleService;
use App\Model\StateDays\StateDayCalendar;
use App\Model\StateDays\StateDayCheck;
use Nette\Application\IPresenterFactory;
use Nette\Application\Request;
use Nette\Application\Responses\JsonResponse;
use Nette\DI\Container;
use Nette\Utils\Json;
use Tester\Assert;
use Tests\Support\FakeArticleInfoSource;
use Tests\Support\FakeArticleSummarySource;
use Tests\Support\FakeClock;
use Tests\Support\FixtureDayPageSource;
use Tests\Support\TestContainer;

require __DIR__ . '/../bootstrap.php';

const Source1 = 'https://www.zakonyprolidi.cz/cs/2000-245#p1';
const Source2 = 'https://www.zakonyprolidi.cz/cs/2000-245#p2';


$container = TestContainer::createWithFreshDatabase();
TestContainer::replace($container, DayPageSource::class, (new FixtureDayPageSource)->serve('10-28', 'day-page-10-28.html', 26193496));
TestContainer::replace($container, ArticleInfoSource::class, new FakeArticleInfoSource);
TestContainer::replace($container, ArticleSummarySource::class, new FakeArticleSummarySource);
TestContainer::replace($container, Clock::class, new FakeClock('2026-10-27 23:30:00'));
TestContainer::replace($container, StateDayCalendar::class, new StateDayCalendar([
	['date' => '10-28', 'name' => 'Den vzniku samostatného československého státu', 'type' => 'state', 'source' => Source1, 'event' => ['year' => 1918, 'text' => 'vyhlášen samostatný československý stát']],
	['date' => '10-28', 'name' => 'Testovací svátek bez události', 'type' => 'other', 'source' => Source2, 'article' => 'Testovací svátek'],
	['date' => '10-28', 'name' => 'Den s chybějící událostí', 'type' => 'significant', 'source' => 'https://www.zakonyprolidi.cz/cs/2000-245#p4', 'event' => ['year' => 1918, 'text' => 'tento text na stránce není']],
	['date' => '10-28', 'name' => 'Den s nejednoznačnou událostí', 'type' => 'significant', 'source' => 'https://www.zakonyprolidi.cz/cs/2000-245#p4', 'event' => ['year' => 1918, 'text' => 'eskoslovensk']], // matches two Czech events of 1918
	['easter' => -2, 'name' => 'Velký pátek', 'type' => 'other', 'source' => Source2, 'article' => 'Velký pátek'],
]));
$container->getByType(DayImportService::class)->import(MonthDay::fromString('10-28'));
$container->getByType(StateDayArticleService::class)->refresh();


/**
 * @param array<string, string> $params
 * @return array<string, mixed>
 */
function day(Container $container, array $params): array
{
	$presenter = $container->getByType(IPresenterFactory::class)->createPresenter('Api:Day');
	$response = $presenter->run(new Request('Api:Day', 'GET', $params));
	assert($response instanceof JsonResponse);
	return Json::decode(Json::encode($response->getPayload()), forceArrays: true);
}


test('a state holiday with an event: the event is in the ten, first, labelled with the official name', function () use ($container) {
	$cards = day($container, ['date' => '10-28'])['cards'];
	Assert::count(10, $cards);
	Assert::same(1918, $cards[0]['year']);
	Assert::contains('vyhlášen samostatný československý stát', $cards[0]['text']);
	Assert::same([
		'id' => 'den-vzniku-samostatneho-ceskoslovenskeho-statu',
		'name' => 'Den vzniku samostatného československého státu',
		'type' => 'state',
		'typeLabel' => 'Státní svátek',
		'law' => 'zákon č. 245/2000 Sb.',
		'lawUrl' => Source1,
	], $cards[0]['stateDay']);
	Assert::same([], array_filter(array_slice($cards, 1), fn($c) => $c['stateDay'] !== null));
	$years = array_column(array_slice($cards, 1), 'year');
	$sorted = $years;
	rsort($sorted);
	Assert::same($sorted, $years); // the rest newest first
});


test('a holiday without an event: its own card with the article introduction and attribution, not among the ten', function () use ($container) {
	$day = day($container, ['date' => '10-28']);
	Assert::count(1, $day['holidays']);
	$holiday = $day['holidays'][0];
	Assert::same(['Testovací svátek bez události', 'Ostatní svátek'], [$holiday['name'], $holiday['typeLabel']]);
	Assert::same('Testovací svátek je svátek.', $holiday['article']['extract']);
	Assert::same('https://cs.wikipedia.org/w/index.php?title=Testovac%C3%AD_sv%C3%A1tek&oldid=123456', $holiday['article']['source']['revisionUrl']);
	Assert::same('https://cs.wikipedia.org/w/index.php?title=Testovac%C3%AD_sv%C3%A1tek&action=history', $holiday['article']['source']['historyUrl']);
	Assert::same('CC BY-SA 4.0', $holiday['article']['source']['license']);
	Assert::count(10, $day['cards']);
});


test('a fragment that matches no event, or more than one, is an error in the check and nothing is guessed', function () use ($container) {
	$results = [];
	foreach ($container->getByType(StateDayCheck::class)->run() as $result) {
		$results[$result['stateDay']->name] = [$result['matches'], $result['first'], $result['ok']];
	}
	Assert::same([1, true, true], $results['Den vzniku samostatného československého státu']);
	Assert::same([0, false, false], $results['Den s chybějící událostí']);
	Assert::true($results['Den s nejednoznačnou událostí'][0] > 1);
	Assert::false($results['Den s nejednoznačnou událostí'][2]);
	Assert::same([0, false, true], $results['Testovací svátek bez události']); // has its article
	$labelled = array_filter(day($container, ['date' => '10-28'])['cards'], fn($c) => $c['stateDay'] !== null);
	Assert::count(1, $labelled);
});


test('movable holidays: only on the date of the given year; a day and month without a year leaves them out', function () use ($container) {
	Assert::same(['Velký pátek'], array_column(day($container, ['date' => '04-03', 'year' => '2026'])['holidays'], 'name'));
	Assert::same([], day($container, ['date' => '04-03', 'year' => '2027'])['holidays']);
	Assert::same([], day($container, ['date' => '04-03'])['holidays']);
	Assert::same(['Velký pátek'], array_column(day($container, ['date' => '03-26', 'year' => '2027'])['holidays'], 'name'));
});
