<?php

/**
 * Rate limiting and retries of the HTTP client, maxlag handling of the Action API.
 */

declare(strict_types=1);

use App\Core\Http\HttpException;
use App\Core\Http\HttpResponse;
use App\Core\Http\PoliteHttpClient;
use App\Model\Import\SourceException;
use App\Model\Wikipedia\ActionApiClient;
use Tester\Assert;
use Tests\Support\FakeClock;
use Tests\Support\FakeHttpClient;

require __DIR__ . '/../bootstrap.php';


function ok(string $body = '{}'): HttpResponse
{
	return new HttpResponse(200, [], $body);
}


test('429 waits exactly as long as Retry-After says, then retries', function () {
	$clock = new FakeClock;
	$http = new FakeHttpClient(new HttpResponse(429, ['Retry-After' => '37'], 'Too many requests'), ok('done'));

	$response = (new PoliteHttpClient($http, $clock))->get('https://example.org/a');

	Assert::same('done', $response->body);
	Assert::same([37.0], $clock->sleeps);
	Assert::count(2, $http->requests);
});


test('503 without Retry-After backs off exponentially from 5 seconds', function () {
	$clock = new FakeClock;
	$http = new FakeHttpClient(new HttpResponse(503, [], ''), new HttpResponse(503, [], ''), ok());

	(new PoliteHttpClient($http, $clock))->get('https://example.org/a');

	Assert::same([5.0, 10.0], $clock->sleeps);
});


test('gateway errors 502 and 504 are retried like 503, other errors are returned', function () {
	$clock = new FakeClock;
	$http = new FakeHttpClient(new HttpResponse(502, [], ''), new HttpResponse(504, [], ''), ok());
	Assert::same(200, (new PoliteHttpClient($http, $clock))->get('https://example.org/a')->status);
	Assert::same([5.0, 10.0], $clock->sleeps);
	$notFound = new FakeHttpClient(new HttpResponse(500, [], ''));
	Assert::same(500, (new PoliteHttpClient($notFound, new FakeClock))->get('https://example.org/b')->status);
});


test('a Retry-After below 5 seconds is raised to the minimum', function () {
	$clock = new FakeClock;
	(new PoliteHttpClient(new FakeHttpClient(new HttpResponse(429, ['retry-after' => '1'], ''), ok()), $clock))->get('https://example.org/a');
	Assert::same([5.0], $clock->sleeps);
});


test('gives up after the maximum number of attempts', function () {
	$http = new FakeHttpClient(...array_fill(0, 4, new HttpResponse(429, ['Retry-After' => '1'], '')));
	Assert::exception(
		fn() => (new PoliteHttpClient($http, new FakeClock, maxAttempts: 4))->get('https://example.org/a'),
		HttpException::class,
		'%a%HTTP 429 after 4 attempts',
	);
});


test('network errors are retried, other HTTP errors are returned to the caller', function () {
	$clock = new FakeClock;
	$http = new FakeHttpClient(new HttpException('timeout'), new HttpResponse(404, [], 'not found'));
	Assert::same(404, (new PoliteHttpClient($http, $clock))->get('https://example.org/a')->status);
	Assert::same([5.0], $clock->sleeps);
});


test('requests are spaced to at most 200 per minute', function () {
	$clock = new FakeClock;
	$client = new PoliteHttpClient(new FakeHttpClient(ok(), ok(), ok()), $clock, requestsPerMinute: 200);
	$client->get('https://example.org/1');
	$client->get('https://example.org/2');
	$clock->advance('+1 second');
	$client->get('https://example.org/3');
	Assert::same([0.3], $clock->sleeps); // only the second request came too early
});


test('maxlag reported in the JSON body of an HTTP 200 response is waited out', function () {
	$clock = new FakeClock;
	$maxlag = new HttpResponse(200, ['Retry-After' => '7', 'X-Database-Lag' => '7'], '{"error":{"code":"maxlag","info":"Waiting for db1: 7 seconds lagged","host":"db1","lag":7}}');
	$http = new FakeHttpClient($maxlag, ok('{"query":{"pages":[]}}'));

	$data = (new ActionApiClient($http, $clock))->query(['action' => 'query', 'titles' => 'Praha']);

	Assert::same(['query' => ['pages' => []]], $data);
	Assert::same([7.0], $clock->sleeps);
	Assert::contains('maxlag=5', $http->requests[0]);
	Assert::contains('formatversion=2', $http->requests[0]);
});


test('persistent maxlag and other API errors are failures, not empty results', function () {
	$maxlag = new HttpResponse(200, [], '{"error":{"code":"maxlag","info":"lagged"}}');
	Assert::exception(
		fn() => (new ActionApiClient(new FakeHttpClient(...array_fill(0, 4, $maxlag)), new FakeClock))->query(['action' => 'query']),
		SourceException::class,
		'%a%lag persists after 4 attempts',
	);
	Assert::exception(
		fn() => (new ActionApiClient(new FakeHttpClient(ok('{"error":{"code":"badvalue","info":"Bad"}}')), new FakeClock))->query(['action' => 'query']),
		SourceException::class,
		'Action API error: badvalue – Bad',
	);
	Assert::exception(
		fn() => (new ActionApiClient(new FakeHttpClient(ok('<html>')), new FakeClock))->query(['action' => 'query']),
		SourceException::class,
		'Action API: invalid JSON',
	);
});
