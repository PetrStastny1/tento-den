<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Clock\Clock;


/**
 * Time stands still unless something sleeps; sleeps are recorded instead of waited.
 */
final class FakeClock implements Clock
{
	/** @var list<float> */
	public array $sleeps = [];

	private \DateTimeImmutable $now;


	public function __construct(string $now = '2026-10-29 12:00:00')
	{
		$this->now = new \DateTimeImmutable($now, new \DateTimeZone('UTC'));
	}


	public function now(): \DateTimeImmutable
	{
		return $this->now;
	}


	public function sleep(float $seconds): void
	{
		$this->sleeps[] = round($seconds, 3);
		$this->now = $this->now->modify(sprintf('+%d microseconds', (int) round($seconds * 1_000_000)));
	}


	public function advance(string $modifier): void
	{
		$this->now = $this->now->modify($modifier);
	}
}
