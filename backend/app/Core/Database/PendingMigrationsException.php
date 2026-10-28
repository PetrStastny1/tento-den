<?php

declare(strict_types=1);

namespace App\Core\Database;


final class PendingMigrationsException extends \RuntimeException
{
	/**
	 * @param list<string> $pending
	 */
	public static function fromPending(array $pending): self
	{
		return new self('Databáze má neprovedené migrace (spusťte make migrate): ' . implode(', ', $pending));
	}
}
