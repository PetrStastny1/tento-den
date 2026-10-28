<?php

declare(strict_types=1);

namespace App\Core\Clock;


final class SystemClock implements Clock
{
	public function now(): \DateTimeImmutable
	{
		return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
	}


	public function sleep(float $seconds): void
	{
		if ($seconds > 0) {
			usleep((int) round($seconds * 1_000_000));
		}
	}
}
