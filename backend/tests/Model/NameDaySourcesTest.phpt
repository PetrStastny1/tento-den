<?php

/**
 * Slovak name days from the introduction of sk.wikipedia day pages, and the check that an article
 * is about a name (Wikidata: instance of a given name), against recorded responses.
 */

declare(strict_types=1);

use App\Core\Http\HttpResponse;
use App\Model\NameDays\Country;
use App\Model\NameDays\NameArticle;
use App\Model\NameDays\NameDayParseException;
use App\Model\NameDays\ParsedNameDay;
use App\Model\NameDays\SlovakNameDayParser;
use App\Model\Wikipedia\ActionApiClient;
use App\Model\Wikipedia\ActionApiNameArticleSource;
use Nette\Utils\FileSystem;
use Tester\Assert;
use Tests\Support\FakeClock;
use Tests\Support\FakeHttpClient;

require __DIR__ . '/../bootstrap.php';

const NameDayFixtures = __DIR__ . '/../fixtures/wikimedia/name-days/';


/**
 * @return list<string>
 */
function slovakNames(string $html): array
{
	return array_map(fn(ParsedNameDay $n) => $n->name, (new SlovakNameDayParser)->parse($html));
}


test('Slovak name days: one, two („a“) or three names in the introduction, none on Christmas Day', function () {
	Assert::same(['Jozef'], slovakNames(FileSystem::read(NameDayFixtures . 'sk-03-19.html')));
	Assert::same(['Andrea', 'Artur'], slovakNames(FileSystem::read(NameDayFixtures . 'sk-01-05.html')));
	Assert::same(['Alexandra', 'Sandra', 'Karina'], slovakNames(FileSystem::read(NameDayFixtures . 'sk-01-02.html')));
	Assert::same([], slovakNames(FileSystem::read(NameDayFixtures . 'sk-12-25.html')));
	$parsed = (new SlovakNameDayParser)->parse(FileSystem::read(NameDayFixtures . 'sk-01-05.html'));
	Assert::same([Country::Slovak, 1, 2], [$parsed[1]->country, $parsed[1]->line, $parsed[1]->position]);
});


test('a mention of name days in any other form fails the day', function () {
	$page = fn(string $lead) => "<html><body><section><p>19. marec je 78. deň roka. $lead</p></section><section><h2>Udalosti</h2><p>Meniny nikoho.</p></section></body></html>";
	Assert::same(['Jozef'], slovakNames($page('Meniny má Jozef.'))); // text after the first h2 is not read
	Assert::exception(fn() => slovakNames($page('Meniny majú Jozef a Jozefína.')), NameDayParseException::class, 'Nečitelná zmínka o meninách v úvodu stránky: %a%');
	Assert::exception(fn() => slovakNames($page('Meniny má Jozef. Meniny má Jozef.')), NameDayParseException::class, 'Úvod stránky zmiňuje meniny víckrát');
	Assert::exception(fn() => slovakNames('<html><body><h2>Udalosti</h2></body></html>'), NameDayParseException::class, 'Stránka nemá úvodní odstavec');
});


test('the article about a name: „(jméno)“ first, redirects followed, only items that are a given name', function () {
	$http = new FakeHttpClient(
		new HttpResponse(200, [], FileSystem::read(NameDayFixtures . 'action-query-name-articles.json')),
		new HttpResponse(200, [], FileSystem::read(NameDayFixtures . 'wbgetentities-name-items.json')),
	);
	$api = new ActionApiClient($http, new FakeClock('2026-10-29 03:00:00'));
	$wikidata = new ActionApiClient($http, new FakeClock('2026-10-29 03:00:00'), ActionApiClient::Wikidata);
	$found = (new ActionApiNameArticleSource($api, $api, $wikidata))->find(['Sibyla', 'Josef', 'Horymír', 'Majoš'], Country::Czech);

	// „Sibyla“ is the prophetess (Q273320), „Sibyla (jméno)“ the female given name
	Assert::equal(new NameArticle('Sibyla (jméno)', 'Q118351913'), $found['Sibyla']);
	Assert::equal(new NameArticle('Josef', 'Q15730712'), $found['Josef']);
	Assert::equal(new NameArticle('Horymír', 'Q12020336'), $found['Horymír']); // „Horymír (jméno)“ redirects here
	Assert::null($found['Majoš']); // no article
	Assert::contains('titles=Sibyla+%28jm%C3%A9no%29%7CSibyla%7CJosef', $http->requests[0]);
	Assert::contains('wbgetentities', $http->requests[1]);
});


test('a disambiguation page is not an article about the name, even with the item of the name', function () {
	$http = new FakeHttpClient(new HttpResponse(200, [], FileSystem::read(NameDayFixtures . 'action-query-albrecht.json')));
	$api = new ActionApiClient($http, new FakeClock('2026-10-29 03:00:00'));
	Assert::same(['Albrecht' => null], (new ActionApiNameArticleSource($api, $api, $api))->find(['Albrecht'], Country::Czech));
	Assert::contains('ppprop=wikibase_item%7Cdisambiguation', $http->requests[0]);
});
