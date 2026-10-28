<?php

/**
 * Wikipedia implementations of the import sources against recorded API responses.
 */

declare(strict_types=1);

use App\Core\Http\HttpResponse;
use App\Model\Events\MonthDay;
use App\Model\Import\SourceException;
use App\Model\Wikipedia\ActionApiArticleInfoSource;
use App\Model\Wikipedia\ActionApiClient;
use App\Model\Wikipedia\WikipediaDayPageSource;
use Tester\Assert;
use Tests\Support\FakeClock;
use Tests\Support\FakeHttpClient;
use Tests\Support\FixtureDayPageSource;

require __DIR__ . '/../bootstrap.php';


function lastRevision(int $revision): HttpResponse
{
	return new HttpResponse(200, [], json_encode(['query' => ['pages' => [['pageid' => 15624, 'ns' => 0, 'title' => '28. říjen', 'lastrevid' => $revision]]]], JSON_THROW_ON_ERROR));
}


function daySource(FakeHttpClient $http): WikipediaDayPageSource
{
	return new WikipediaDayPageSource(new ActionApiClient($http, new FakeClock), $http);
}


test('loads Parsoid HTML of exactly the current revision', function () {
	$http = new FakeHttpClient(lastRevision(26193496), new HttpResponse(200, ['Content-Revision-Id' => '26193496'], '<html>…</html>'));

	$page = daySource($http)->fetch(MonthDay::fromString('10-28'));

	Assert::same(['28. říjen', 26193496, '<html>…</html>'], [$page->title, $page->revisionId, $page->html]);
	Assert::contains('titles=28.+%C5%99%C3%ADjen', $http->requests[0]);
	Assert::same('https://cs.wikipedia.org/api/rest_v1/page/html/28._%C5%99%C3%ADjen/26193496', $http->requests[1]);
});


test('HTML of another revision than requested is rejected', function () {
	$http = new FakeHttpClient(lastRevision(26193496), new HttpResponse(200, ['Content-Revision-Id' => '1'], '<html>other page</html>'));
	Assert::exception(fn() => daySource($http)->fetch(MonthDay::fromString('10-28')), SourceException::class, '%a%expected revision 26193496, got 1');
});


test('a missing day page or a failed HTML request is an error, not an empty day', function () {
	$missing = new HttpResponse(200, [], '{"query":{"pages":[{"ns":0,"title":"28. říjen","missing":true}]}}');
	Assert::exception(fn() => daySource(new FakeHttpClient($missing))->fetch(MonthDay::fromString('10-28')), SourceException::class, '%a%not found');

	$http = new FakeHttpClient(lastRevision(26193496), new HttpResponse(500, [], ''));
	Assert::exception(fn() => daySource($http)->fetch(MonthDay::fromString('10-28')), SourceException::class, '%a%HTTP 500');
});


test('article info follows redirects and keeps the requested title as key (recorded response)', function () {
	$recorded = json_decode((string) file_get_contents(FixtureDayPageSource::Dir . '/action-query-info-pageprops-pageviews.json'), true, flags: JSON_THROW_ON_ERROR);
	unset($recorded['continue']); // the recording also asked for pageviews, which paginate; the source does not
	$http = new FakeHttpClient(new HttpResponse(200, [], json_encode($recorded, JSON_THROW_ON_ERROR)));

	$articles = (new ActionApiArticleInfoSource(new ActionApiClient($http, new FakeClock)))->fetch(['Husité', 'Pochod na Řím']);

	Assert::same('Husitství', $articles['Husité']?->title);
	Assert::same([286503, 25411248, 'Q468823'], [$articles['Pochod na Řím']?->pageId, $articles['Pochod na Řím']?->revisionId, $articles['Pochod na Řím']?->wikibaseItem]);
	Assert::contains('redirects=1', $http->requests[0]);
});


test('a page that does not exist resolves to null', function () {
	$response = new HttpResponse(200, [], '{"query":{"pages":[{"ns":0,"title":"Neexistující článek","missing":true}]}}');
	$articles = (new ActionApiArticleInfoSource(new ActionApiClient(new FakeHttpClient($response), new FakeClock)))->fetch(['Neexistující článek']);
	Assert::same(['Neexistující článek' => null], $articles);
});
