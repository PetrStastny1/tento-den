<?php

declare(strict_types=1);

namespace App\Model\NameDays;


/**
 * A stored name day with its source (the day page revision).
 */
final readonly class NameDay
{
	public function __construct(
		public Country $country,
		public string $name,
		public int $line,
		public ?string $note,
		public string $sourceTitle,
		public int $sourceRevisionId,
		public \DateTimeImmutable $fetchedAt,
	) {
	}
}
