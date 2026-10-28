<?php

declare(strict_types=1);

namespace App\Core\Clock;


/**
 * Current time and waiting, injectable so tests never really sleep.
 */
interface Clock
{
	public function now(): \DateTimeImmutable;

	public function sleep(float $seconds): void;
}
