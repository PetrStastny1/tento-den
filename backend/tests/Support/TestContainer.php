<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Bootstrap;
use App\Core\Database\Migrations;
use Nette\Database\Connection;
use Nette\DI\Container;
use Nextras\Migrations\Bridges\NetteDatabase\NetteAdapter;
use Nextras\Migrations\Drivers\MySqlDriver;
use Nextras\Migrations\Printers\DevNull;
use Tester\Environment;


/**
 * Containers bound to the test database (DB_TEST_NAME) and helpers to swap services for fakes.
 */
final class TestContainer
{
	/**
	 * @param array<string, string> $env overrides of the environment
	 */
	public static function create(array $env = []): Container
	{
		$base = getenv();
		$base['DB_NAME'] = $base['DB_TEST_NAME'] ?? throw new \LogicException('DB_TEST_NAME is not set');
		return (new Bootstrap($env + $base))->createContainer();
	}


	/**
	 * Serializes database tests (Tester runs files in parallel) and starts from a fresh schema.
	 * @param array<string, string> $env
	 */
	public static function createWithFreshDatabase(array $env = []): Container
	{
		self::lockDatabase();
		$container = self::create($env);
		self::emptyDatabase($container);
		$container->getByType(Migrations::class)->migrate(new DevNull);
		return $container;
	}


	public static function lockDatabase(): void
	{
		Environment::lock('database', __DIR__ . '/../../temp');
	}


	public static function emptyDatabase(Container $container): void
	{
		(new MySqlDriver(new NetteAdapter($container->getByType(Connection::class))))->emptyDatabase();
	}


	/**
	 * Replaces the autowired service of the given type (must not be instantiated yet).
	 * @param class-string $type
	 */
	public static function replace(Container $container, string $type, object $service): void
	{
		$names = $container->findAutowired($type); // e.g. the sk.wikipedia DayPageSource is not autowired
		if (count($names) !== 1) {
			throw new \LogicException("Expected exactly one service of type $type");
		}
		$container->removeService($names[0]);
		$container->addService($names[0], $service);
	}
}
