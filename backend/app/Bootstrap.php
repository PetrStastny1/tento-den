<?php

declare(strict_types=1);

namespace App;

use App\Core\Config\AppConfig;
use App\Core\Config\EnvironmentConfigLoader;
use App\Core\Database\PendingMigrationsCheck;
use Nette\Bootstrap\Configurator;
use Nette\DI\Container;


/**
 * Builds the DI container. Environment variables are passed in explicitly
 * (instead of being read globally) and validated before anything else happens.
 */
final class Bootstrap
{
	private readonly string $rootDir;
	private readonly AppConfig $config;


	/**
	 * @param array<string, string> $env
	 * @throws Core\Config\InvalidConfigurationException
	 */
	public function __construct(array $env)
	{
		$this->rootDir = dirname(__DIR__);
		$this->config = (new EnvironmentConfigLoader)->load($env);
	}


	/**
	 * Web entry point: production refuses to run with pending migrations.
	 * @throws Core\Database\PendingMigrationsException
	 */
	public function bootWebApplication(): Container
	{
		$configurator = $this->createConfigurator();
		$configurator->enableTracy($this->rootDir . '/log');
		$container = $configurator->createContainer();
		$container->getByType(PendingMigrationsCheck::class)->check();
		return $container;
	}


	/**
	 * Console entry point: logs to log/ like the web, but without the migration check,
	 * so "make migrate" works on any database.
	 */
	public function bootConsoleApplication(): Container
	{
		$configurator = $this->createConfigurator();
		$configurator->enableTracy($this->rootDir . '/log');
		return $configurator->createContainer();
	}


	/**
	 * Tests: no Tracy, no migration check; loggers are replaced where a test needs them.
	 */
	public function createContainer(): Container
	{
		return $this->createConfigurator()->createContainer();
	}


	public function getConfig(): AppConfig
	{
		return $this->config;
	}


	private function createConfigurator(): Configurator
	{
		$configurator = new Configurator;
		$configurator->setDebugMode($this->config->isDebugMode());
		$configurator->setTempDirectory($this->rootDir . '/temp');
		// dynamic parameters are resolved at runtime and never compiled into the cached container
		$configurator->addDynamicParameters([
			'database' => [
				'dsn' => $this->config->database->dsn(),
				'user' => $this->config->database->user,
				'password' => $this->config->database->password,
			],
		]);
		$configurator->addConfig($this->rootDir . '/config/common.neon');
		$configurator->addConfig($this->rootDir . '/config/services.neon');
		$configurator->addConfig($this->rootDir . '/config/generic-articles.neon');
		$configurator->addConfig($this->rootDir . '/config/day-page-rules.neon');
		$configurator->addConfig($this->rootDir . '/config/selection.neon');
		$configurator->addConfig($this->rootDir . '/config/state-days.neon');
		$configurator->addConfig($this->rootDir . '/config/name-day-rules.neon');
		$configurator->addConfig($this->rootDir . '/config/name-bearer-exclusions.neon');
		$configurator->addConfig($this->rootDir . '/config/anniversaries.neon');
		$configurator->addConfig($this->rootDir . '/config/anniversary-exclusions.neon');
		$configurator->addServices(['appConfig' => $this->config]);
		return $configurator;
	}
}
