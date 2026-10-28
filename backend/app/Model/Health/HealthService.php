<?php

declare(strict_types=1);

namespace App\Model\Health;

use Nette\Database\DriverException;
use Tracy\ILogger;


final class HealthService
{
	public function __construct(
		private readonly HealthRepository $repository,
		private readonly ILogger $logger,
	) {
	}


	public function check(): HealthStatus
	{
		try {
			$this->repository->ping();
		} catch (DriverException $e) {
			// the reason goes to the log only, never to the public response
			$this->logger->log($e, ILogger::ERROR);
			return new HealthStatus(healthy: false);
		}

		return new HealthStatus(healthy: true);
	}
}
