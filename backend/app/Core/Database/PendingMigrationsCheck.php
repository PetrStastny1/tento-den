<?php

declare(strict_types=1);

namespace App\Core\Database;

use App\Core\Config\AppConfig;
use Tracy\ILogger;


/**
 * Runs on every web request (never from the CLI, so it cannot block "make migrate"):
 * production refuses to serve on an outdated schema, development only warns.
 */
final class PendingMigrationsCheck
{
	public function __construct(
		private readonly Migrations $migrations,
		private readonly AppConfig $config,
		private readonly ILogger $logger,
	) {
	}


	/**
	 * @throws PendingMigrationsException in production
	 */
	public function check(): void
	{
		$pending = $this->migrations->pending();
		if ($pending === []) {
			return;
		}

		$exception = PendingMigrationsException::fromPending($pending);
		if ($this->config->isProduction()) {
			throw $exception;
		}
		$this->logger->log($exception->getMessage(), ILogger::WARNING);
	}
}
