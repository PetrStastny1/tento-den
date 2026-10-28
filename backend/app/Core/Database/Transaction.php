<?php

declare(strict_types=1);

namespace App\Core\Database;

use Nette\Database\Explorer;


/**
 * Runs several repository calls atomically; rolls back on any exception.
 */
final class Transaction
{
	public function __construct(
		private readonly Explorer $database,
	) {
	}


	/**
	 * @template T
	 * @param callable(): T $callback
	 * @return T
	 */
	public function run(callable $callback): mixed
	{
		return $this->database->transaction(fn() => $callback());
	}
}
