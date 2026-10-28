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
	 * CLI and tests: no migration check, so "make migrate" works on any database.
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
		$configurator->addServices(['appConfig' => $this->config]);
		return $configurator;
	}
}
