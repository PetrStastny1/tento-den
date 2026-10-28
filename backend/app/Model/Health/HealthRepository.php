<?php

declare(strict_types=1);

namespace App\Model\Health;

use Nette\Database\Explorer;


final class HealthRepository
{
	public function __construct(
		private readonly Explorer $database,
	) {
	}


	/**
	 * @throws \Nette\Database\DriverException when the database cannot be reached
	 */
	public function ping(): void
	{
		$this->database->query('SELECT 1');
	}
}
