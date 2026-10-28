<?php

/**
 * Migrations on an empty database and the pending-migrations check of the web entry point.
 */

declare(strict_types=1);

use App\Bootstrap;
use App\Core\Database\Migrations;
use App\Core\Database\PendingMigrationsCheck;
use App\Core\Database\PendingMigrationsException;
use Nette\Database\Explorer;
use Nette\DI\Container;
use Nextras\Migrations\Printers\DevNull;
use Tester\Assert;
use Tests\Support\MemoryLogger;
use Tests\Support\TestContainer;
use Tracy\ILogger;

require __DIR__ . '/../bootstrap.php';

TestContainer::lockDatabase();


function emptyDatabase(string $environment): Container
{
	$container = TestContainer::create(['APP_ENV' => $environment]);
	TestContainer::emptyDatabase($container);
	return $container;
}


test('an empty database has every migration pending', function () {
	$migrations = emptyDatabase('production')->getByType(Migrations::class);
	$files = array_map('basename', glob(__DIR__ . '/../../migrations/structures/*.sql') ?: []);
	Assert::true(count($files) >= 2);
	Assert::same($files, $migrations->pending());
});


test('production refuses to serve with pending migrations', function () {
	$check = emptyDatabase('production')->getByType(PendingMigrationsCheck::class);
	Assert::exception(fn() => $check->check(), PendingMigrationsException::class, '%a%make migrate%a%2026-10-29-001-events.sql%a%');
});


test('development only logs a warning', function () {
	$container = emptyDatabase('development');
	$logger = new MemoryLogger;
	TestContainer::replace($container, ILogger::class, $logger);

	$container->getByType(PendingMigrationsCheck::class)->check();

	Assert::count(1, $logger->entries);
	Assert::same(ILogger::WARNING, $logger->entries[0][1]);
});


test('migrations run on an empty database even in production (the CLI container skips the check)', function () {
	$container = emptyDatabase('production');
	$container->getByType(Migrations::class)->migrate(new DevNull);

	Assert::same([], $container->getByType(Migrations::class)->pending());
	$tables = $container->getByType(Explorer::class)->fetchPairs('SHOW TABLES');
	foreach (['events', 'articles', 'event_articles', 'import_days', 'import_runs', 'migrations'] as $table) {
		Assert::contains($table, $tables);
	}
	$container->getByType(PendingMigrationsCheck::class)->check(); // no exception any more
});


test('the year 0 is rejected by the database as well', function () {
	$container = emptyDatabase('production');
	$container->getByType(Migrations::class)->migrate(new DevNull);
	Assert::exception(
		fn() => $container->getByType(Explorer::class)->query("INSERT INTO events (month, day, year, section, position, text, text_hash, source_title, source_revision_id, has_primary_article, fetched_at) VALUES (1, 1, 0, 'cz', 1, 'x', 'x', 'x', 1, 0, NOW())"),
		Nette\Database\DriverException::class,
		'%a%chk_events_year%a%',
	);
});


test('the web bootstrap runs the check, the CLI container does not', function () {
	$env = ['DB_NAME' => (string) getenv('DB_TEST_NAME'), 'APP_ENV' => 'production'] + getenv();
	TestContainer::emptyDatabase((new Bootstrap($env))->createContainer());

	Assert::exception(fn() => (new Bootstrap($env))->bootWebApplication(), PendingMigrationsException::class);
	Assert::type(Container::class, (new Bootstrap($env))->createContainer());
});
