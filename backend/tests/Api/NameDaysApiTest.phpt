<?php

/**
 * Name days in GET /api/day: CZ from the cs.wikipedia day page (imported with the events), SK from
 * the sk.wikipedia day page, each with its source; meaning and bearer only when found.
 */

declare(strict_types=1);

use App\Core\Clock\Clock;
use App\Core\Database\Transaction;
use App\Model\Events\MonthDay;
use App\Model\Import\ArticleInfoSource;
use App\Model\Import\DayImportService;
use App\Model\Import\DayPageSource;
use App\Model\NameDays\Country;
use App\Model\NameDays\NameBearerExclusions;
use App\Model\NameDays\NameDayRepository;
use App\Model\NameDays\NameInfoRepository;
use App\Model\NameDays\NameInfoService;
use App\Model\NameDays\SlovakNameDayImportService;
use App\Model\NameDays\SlovakNameDayParser;
use Nette\Application\IPresenterFactory;
use Nette\Application\Request;
use Nette\Application\Responses\JsonResponse;
use Nette\Utils\Json;
use Tester\Assert;
use Tests\Support\FakeArticleInfoSource;
use Tests\Support\FakeArticleSummarySource;
use Tests\Support\FakeBearerSource;
use Tests\Support\FakeClock;
use Tests\Support\FakeNameArticleSource;
use Tests\Support\FixtureDayPageSource;
use Tests\Support\TestContainer;

require __DIR__ . '/../bootstrap.php';


$container = TestContainer::createWithFreshDatabase();
$clock = new FakeClock('2026-10-27 23:30:00');
TestContainer::replace($container, DayPageSource::class, (new FixtureDayPageSource)->serve('10-28', 'day-page-10-28.html', 26193496));
TestContainer::replace($container, ArticleInfoSource::class, new FakeArticleInfoSource);
TestContainer::replace($container, Clock::class, $clock);
$container->getByType(DayImportService::class)->import(MonthDay::fromString('10-28'));

// the sk.wikipedia page of 19. 3. stands in for 28. 10. – only the parsing and storing matter here
$repository = $container->getByType(NameDayRepository::class);
$skPages = (new FixtureDayPageSource)->serve('10-28', 'name-days/sk-03-19.html', 8184356);
(new SlovakNameDayImportService($skPages, new SlovakNameDayParser, $repository, $container->getByType(Transaction::class), $clock))->import(MonthDay::fromString('10-28'));

$names = new NameInfoService(
	$repository,
	$container->getByType(NameInfoRepository::class),
	new FakeNameArticleSource(['Juda', 'Tadeáš', 'Jozef']),
	new FakeArticleSummarySource,
	new FakeArticleSummarySource,
	new FakeBearerSource(['Juda' => 'Juda Tadeáš (apoštol)', 'Jozef' => 'Jozef Gabčík']),
	new NameBearerExclusions([]),
	$clock,
);
$names->refresh(Country::Czech, false);
$names->refresh(Country::Slovak, false);

$presenter = $container->getByType(IPresenterFactory::class)->createPresenter('Api:Day');
$response = $presenter->run(new Request('Api:Day', 'GET', ['date' => '10-28']));
assert($response instanceof JsonResponse);
$nameDays = Json::decode(Json::encode($response->getPayload()), forceArrays: true)['nameDays'];


test('CZ: the main entry of the cs page with meaning and bearer, other names apart, source revision', function () use ($nameDays) {
	$cz = $nameDays['cz'];
	Assert::same(['cz', 'Česko', 'cs', 'Svátek má'], [$cz['country'], $cz['label'], $cz['language'], $cz['heading']]);
	Assert::same(['Juda', 'Tadeáš'], array_column($cz['names'], 'name'));
	Assert::same([['name' => 'Alfréd', 'note' => null]], $cz['otherNames']);
	Assert::same('Juda (jméno) je svátek.', $cz['names'][0]['meaning']['extract']);
	Assert::same('https://cs.wikipedia.org/w/index.php?title=Juda_%28jm%C3%A9no%29&oldid=123456', $cz['names'][0]['meaning']['source']['revisionUrl']);
	Assert::same('CC BY-SA 4.0', $cz['names'][0]['meaning']['source']['license']);
	Assert::same(['name' => 'Juda Tadeáš (apoštol)', 'wikidataId' => 'Q42', 'url' => 'https://cs.wikipedia.org/wiki/Juda_Tade%C3%A1%C5%A1_%28apo%C5%A1tol%29'], $cz['names'][0]['bearer']);
	// every name of the main entry has its own meaning; a bearer only where one was found
	Assert::same('Tadeáš (jméno) je svátek.', $cz['names'][1]['meaning']['extract']);
	Assert::null($cz['names'][1]['bearer']);
	Assert::same('https://cs.wikipedia.org/w/index.php?title=28._%C5%99%C3%ADjen&oldid=26193496', $cz['source']['revisionUrl']);
});


test('SK: from sk.wikipedia, in Slovak, links and attribution to sk.wikipedia', function () use ($nameDays) {
	$sk = $nameDays['sk'];
	Assert::same(['sk', 'Slovensko', 'sk', 'Meniny má'], [$sk['country'], $sk['label'], $sk['language'], $sk['heading']]);
	Assert::same(['Jozef'], array_column($sk['names'], 'name'));
	Assert::match('https://sk.wikipedia.org/w/index.php?title=%a%&oldid=8184356', $sk['source']['revisionUrl']); // the fixture source names the page in Czech
	Assert::same('sk.wikipedia.org', $sk['source']['wiki']);
	Assert::same('https://sk.wikipedia.org/w/index.php?title=Jozef_%28jm%C3%A9no%29&oldid=123456', $sk['names'][0]['meaning']['url']);
	Assert::same('https://sk.wikipedia.org/wiki/Jozef_Gab%C4%8D%C3%ADk', $sk['names'][0]['bearer']['url']);
});


test('a refresh without --all looks up only names not checked yet', function () use ($names) {
	Assert::same(['names' => 0, 'meaning' => 0, 'bearer' => 0], $names->refresh(Country::Czech, false));
	Assert::same(['names' => 2, 'meaning' => 2, 'bearer' => 1], $names->refresh(Country::Czech, true));
});
