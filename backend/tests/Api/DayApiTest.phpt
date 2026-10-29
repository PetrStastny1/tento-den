<?php

/**
 * GET /api/day and GET /api/events/{id} against the test database with an imported fixture day.
 */

declare(strict_types=1);

use App\Api\ApiException;
use App\Core\Clock\Clock;
use App\Model\Events\ArticleRepository;
use App\Model\Events\MonthDay;
use App\Model\Import\ArticleInfoSource;
use App\Model\Import\DayImportService;
use App\Model\Import\DayPageSource;
use Nette\Application\IPresenterFactory;
use Nette\Application\Request;
use Nette\Application\Responses\JsonResponse;
use Nette\DI\Container;
use Nette\Http\UrlScript;
use Nette\Routing\Router;
use Nette\Utils\Json;
use Tester\Assert;
use Tests\Support\FakeArticleInfoSource;
use Tests\Support\FakeClock;
use Tests\Support\FixtureDayPageSource;
use Tests\Support\TestContainer;

require __DIR__ . '/../bootstrap.php';


/**
 * Imports 28. 10. once; "now" is 27. 10. 23:30 UTC = 28. 10. 00:30 in Prague.
 */
function apiContainer(): Container
{
	$container = TestContainer::createWithFreshDatabase();
	TestContainer::replace($container, DayPageSource::class, (new FixtureDayPageSource)->serve('10-28', 'day-page-10-28.html', 26193496));
	TestContainer::replace($container, ArticleInfoSource::class, new FakeArticleInfoSource);
	TestContainer::replace($container, Clock::class, new FakeClock('2026-10-27 23:30:00'));
	$container->getByType(DayImportService::class)->import(MonthDay::fromString('10-28'));

	$sitelinks = ['Vznik Československa' => 8, 'Socha Svobody' => 151, 'Harvardova univerzita' => 137, 'Pochod na Řím' => 47, 'Kryštof Kolumbus' => 180];
	$items = [];
	foreach ($sitelinks as $title => $count) {
		$items['Q' . FakeArticleInfoSource::pageId($title)] = $count;
	}
	$container->getByType(ArticleRepository::class)->updateSitelinks($items, new DateTimeImmutable);
	return $container;
}


/**
 * @param array<string, mixed> $params
 * @return array<string, mixed>
 */
function call(Container $container, string $presenter, array $params = [], string $method = 'GET'): array
{
	$response = $container->getByType(IPresenterFactory::class)->createPresenter($presenter)->run(new Request($presenter, $method, $params));
	assert($response instanceof JsonResponse);
	return Json::decode(Json::encode($response->getPayload()), forceArrays: true);
}


$container = apiContainer();


test('GET /api/day?date=10-28: ten cards, at least three Czech, newest first, with attribution', function () use ($container) {
	$day = call($container, 'Api:Day', ['date' => '10-28']);

	Assert::same('10-28', $day['date']);
	Assert::count(10, $day['cards']);
	Assert::true(count(array_filter($day['cards'], fn($c) => $c['section'] === 'cz')) >= 3);
	$years = array_column(array_filter($day['cards'], fn($c) => $c['stateDay'] === null), 'year');
	$sorted = $years;
	rsort($sorted);
	Assert::same($sorted, $years);
	foreach ($day['cards'] as $card) {
		Assert::same(['id', 'date', 'year', 'yearLabel', 'section', 'text', 'sensitive', 'stateDay', 'source', 'article'], array_keys($card));
		Assert::same('CC BY-SA 4.0', $card['source']['license']);
		Assert::same('https://cs.wikipedia.org/w/index.php?title=28._%C5%99%C3%ADjen&oldid=26193496', $card['source']['revisionUrl']);
		Assert::same('https://cs.wikipedia.org/w/index.php?title=28._%C5%99%C3%ADjen&action=history', $card['source']['historyUrl']);
		Assert::type('bool', $card['sensitive']);
	}
	Assert::contains('Socha Svobody', array_column(array_filter(array_column($day['cards'], 'article')), 'title'));
});


test('the same day gives the same ten', function () use ($container) {
	Assert::same(call($container, 'Api:Day', ['date' => '10-28']), call($container, 'Api:Day', ['date' => '10-28']));
});


test('without a date, today in Europe/Prague is used', function () use ($container) {
	Assert::same('10-28', call($container, 'Api:Day')['date']); // 23:30 UTC on 27. 10. is already 28. 10. in Prague
});


test('a day without events has no cards', function () use ($container) {
	Assert::same(['date' => '02-29', 'holidays' => [], 'cards' => []], call($container, 'Api:Day', ['date' => '02-29']));
});


test('invalid input is a 400 with a readable message, other methods 405', function () use ($container) {
	foreach (['13-01' => 'Datum 13-01 neexistuje.', '02-30' => 'Datum 02-30 neexistuje.', '1028' => 'Parametr date musí mít tvar MM-DD, např. 10-28.', '' => 'Parametr date musí mít tvar MM-DD, např. 10-28.'] as $date => $message) {
		$e = Assert::exception(fn() => call($container, 'Api:Day', ['date' => (string) $date]), ApiException::class, $message);
		assert($e instanceof ApiException);
		Assert::same(400, $e->getHttpCode());
	}
	$e = Assert::exception(fn() => call($container, 'Api:Day', ['date' => '10-28'], 'POST'), ApiException::class);
	assert($e instanceof ApiException);
	Assert::same(405, $e->getHttpCode()); // the Allow header cannot be observed in CLI (headers_list() is empty)
});


test('GET /api/events/{id} returns the card with its status; unknown id is 404', function () use ($container) {
	$card = call($container, 'Api:Day', ['date' => '10-28'])['cards'][0];
	$event = call($container, 'Api:Event', ['id' => (string) $card['id']]);
	Assert::same($card + ['status' => 'active'], $event);

	$e = Assert::exception(fn() => call($container, 'Api:Event', ['id' => '999999']), ApiException::class, 'Událost nenalezena.');
	assert($e instanceof ApiException);
	Assert::same(404, $e->getHttpCode());
});


test('routes accept only numeric event ids', function () use ($container) {
	$router = $container->getByType(Router::class);
	$match = fn(string $path) => $router->match(new Nette\Http\Request(new UrlScript("http://localhost$path", '/')));
	Assert::equal(['presenter' => 'Api:Event', 'action' => 'default', 'id' => '42'], $match('/api/events/42'));
	Assert::null($match('/api/events/abc'));
	Assert::same('10-28', $match('/api/day?date=10-28')['date'] ?? null);
});


test('client errors keep their message in the JSON error, internal errors do not', function () use ($container) {
	$error = fn(Throwable $e) => call($container, 'Api:Error', ['exception' => $e]);
	Assert::same(['error' => ['code' => 400, 'message' => 'Datum 13-01 neexistuje.']], $error(ApiException::invalidRequest('Datum 13-01 neexistuje.')));
	Assert::same(['error' => ['code' => 404, 'message' => 'Not Found']], $error(new Nette\Application\BadRequestException('No route for /secret/path')));
});
