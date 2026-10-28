<?php

declare(strict_types=1);

namespace App;

use Nette\Bootstrap\Configurator;
use Nette\DI\Container;


/**
 * Builds the DI container. Environment variables are passed in explicitly
 * (instead of being read globally) so tests can boot with their own values.
 */
final class Bootstrap
{
	private readonly string $rootDir;


	/**
	 * @param array<string, string> $env
	 */
	public function __construct(
		private readonly array $env,
	) {
		$this->rootDir = dirname(__DIR__);
	}


	public function bootWebApplication(): Container
	{
		$configurator = $this->createConfigurator();
		$configurator->enableTracy($this->rootDir . '/log');
		return $configurator->createContainer();
	}


	public function bootTestContainer(): Container
	{
		return $this->createConfigurator()->createContainer();
	}


	private function createConfigurator(): Configurator
	{
		$configurator = new Configurator;
		// anything other than an explicit "development" runs in production mode
		$configurator->setDebugMode(($this->env['APP_ENV'] ?? '') === 'development');
		$configurator->setTempDirectory($this->rootDir . '/temp');
		// dynamic parameters are resolved at runtime and never compiled into the cached container
		$configurator->addDynamicParameters(['env' => $this->env]);
		$configurator->addConfig($this->rootDir . '/config/common.neon');
		$configurator->addConfig($this->rootDir . '/config/services.neon');
		return $configurator;
	}
}
