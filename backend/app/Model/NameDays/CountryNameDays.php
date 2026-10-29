<?php

declare(strict_types=1);

namespace App\Model\NameDays;


/**
 * The name days of one day in one mode: the main entry (line 1) with what is known about each name,
 * the other names of the page, and the source page revision.
 */
final readonly class CountryNameDays
{
	/**
	 * @param list<array{nameDay: NameDay, info: ?NameInfo, variantOf: ?array{name: string, rule: string}}> $names
	 * @param list<NameDay> $otherNames
	 */
	public function __construct(
		public Country $country,
		public array $names,
		public array $otherNames,
		public string $sourceTitle,
		public int $sourceRevisionId,
		public \DateTimeImmutable $fetchedAt,
	) {
	}
}
