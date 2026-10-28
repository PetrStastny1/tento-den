<?php

/**
 * Console dispatcher, argument validation and exit codes of the import commands.
 */

declare(strict_types=1);

use App\Core\Clock\Clock;
use App\Core\Console\ConsoleApplication;
use App\Core\Console\Output;
use App\Model\Events\MonthDay;
use App\Model\Import\ArticleInfoSource;
use App\Model\Import\DayPageSource;
use Nette\DI\Container;
use Tester\Assert;
use Tests\Support\FakeArticleInfoSource;
use Tests\Support\FakeClock;
use Tests\Support\FixtureDayPageSource;
use Tests\Support\TestContainer;

require __DIR__ . '/../bootstrap.php';


/**
 * @param list<string> $argv
 * @return array{int, string, string} exit code, stdout, stderr
 */
function runConsole(Container $container, array $argv): array
{
	$stdout = fopen('php://memory', 'w+');
	$stderr = fopen('php://memory', 'w+');
	assert(is_resource($stdout) && is_resource($stderr));
	$code = $container->getByType(ConsoleApplication::class)->run($argv, new Output($stdout, $stderr));
	rewind($stdout);
	rewind($stderr);
	return [$code, (string) stream_get_contents($stdout), (string) stream_get_contents($stderr)];
}


function pageWithOneEvent(MonthDay $day): string
{
	return '<html><body><section><h2>Události</h2><section><h3>Česko</h3><ul><li>2000 – Událost dne '
		. $day->format() . '.</li></ul></section></section></body></html>';
}


/**
 * @return array{Container, FixtureDayPageSource}
 */
function consoleWithDatabase(): array
{
	$container = TestContainer::createWithFreshDatabase();
	$pages = new FixtureDayPageSource;
	TestContainer::replace($container, DayPageSource::class, $pages);
	TestContainer::replace($container, ArticleInfoSource::class, new FakeArticleInfoSource);
	TestContainer::replace($container, Clock::class, new FakeClock);
	return [$container, $pages];
}


test('unknown command and invalid arguments end with exit code 2 and a hint', function () {
	$container = TestContainer::create();
	[$code, $stdout, $stderr] = runConsole($container, ['import:wat']);
	Assert::same(2, $code);
	Assert::contains('Neznámý příkaz: import:wat', $stderr);
	Assert::contains('import:day', $stdout);

	foreach ([['import:day'], ['import:day', '--date=13-01'], ['import:day', '--date=02-30'], ['import:day', '10-28'], ['import:year', '--resume=yes'], ['import:day', '--date=10-28', '--force']] as $argv) {
		[$code, , $stderr] = runConsole($container, $argv);
		Assert::same(2, $code, implode(' ', $argv));
		Assert::notSame('', $stderr);
	}
});


test('import:day reports the result, or the failure with exit code 1', function () {
	[$container, $pages] = consoleWithDatabase();
	$pages->serve('10-28', 'day-page-10-28.html', 26193496);

	[$code, $stdout] = runConsole($container, ['import:day', '--date=10-28']);
	Assert::same(0, $code);
	Assert::same("10-28 ok (revize 26193496): Česko 15, Svět 16 | nové 31, upravené 0, beze změny 0, zmizelé 0, k revizi 0\n", $stdout);

	$pages->fail('10-28');
	[$code, , $stderr] = runConsole($container, ['import:day', '--date=10-28']);
	Assert::same(1, $code);
	Assert::contains('Import dne 10-28 selhal', $stderr);
});


test('import:year continues after failures, lists them, exits 1; --resume retries only them', function () {
	[$container, $pages] = consoleWithDatabase();
	foreach (MonthDay::all() as $day) {
		$pages->serveHtml($day->format(), pageWithOneEvent($day), 1000 + $day->month * 100 + $day->day);
	}
	$pages->fail('03-15')->fail('11-30');

	[$code, $stdout, $stderr] = runConsole($container, ['import:year']);
	Assert::same(1, $code);
	Assert::contains('[366/366] 12-31 ok', $stdout);
	Assert::contains('Neúspěšné dny (2): 03-15, 11-30', $stderr);

	$pages->serveHtml('03-15', pageWithOneEvent(MonthDay::fromString('03-15')), 1);
	$pages->serveHtml('11-30', pageWithOneEvent(MonthDay::fromString('11-30')), 1);
	[$code, $stdout] = runConsole($container, ['import:year', '--resume']);
	Assert::same(0, $code);
	Assert::same(364, substr_count($stdout, 'přeskočeno'));
	Assert::contains('03-15 ok', $stdout);
	Assert::contains('11-30 ok', $stdout);
	Assert::contains('Hotovo, všechny dny v pořádku.', $stdout);
});


test('events:review lists events waiting for a decision', function () {
	[$container, $pages] = consoleWithDatabase();
	$pages->serve('01-01', 'history/day-page-01-01-rev24784971.html', 24784971);
	runConsole($container, ['import:day', '--date=01-01']);
	$pages->serve('01-01', 'history/day-page-01-01-rev24807572.html', 24807572);
	runConsole($container, ['import:day', '--date=01-01']);

	[$code, $stdout] = runConsole($container, ['events:review']);
	Assert::same(0, $code);
	Assert::contains('Celkem k revizi: 2', $stdout);
	Assert::match('%A%01-01 cz 1770: Císařovna Marie Terezie vydala nový trestní zákoník%A%Možná nahrazena událostí #%d% (revize 24807572, podobnost 0.36)%A%', $stdout);
});
