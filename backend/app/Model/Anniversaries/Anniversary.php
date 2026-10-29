<?php

declare(strict_types=1);

namespace App\Model\Anniversaries;


/**
 * The round anniversary of the day: the event, how many years and why it counts as big.
 */
final readonly class Anniversary
{
	public function __construct(
		public int $eventId,
		public int $years,
		public string $reason,
	) {
	}


	public function label(): string
	{
		return RoundAnniversary::label($this->years);
	}
}
