<?php

declare(strict_types=1);

namespace Tests;

use Tracy\ILogger;


/**
 * Collects log entries in memory so tests can assert on them without touching log/.
 */
final class MemoryLogger implements ILogger
{
	/** @var list<array{mixed, string}> */
	public array $entries = [];


	public function log(mixed $value, string $level = self::INFO): void
	{
		$this->entries[] = [$value, $level];
	}
}
