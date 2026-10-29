<?php

declare(strict_types=1);

namespace App\Model\NameDays;

use App\Model\Import\ArticleSummary;


/**
 * What is known about a name in one mode: the introduction of its article and a known bearer.
 */
final readonly class NameInfo
{
	public function __construct(
		public string $name,
		public Country $country,
		public ?string $wikidataItem,
		public ?ArticleSummary $meaning,
		public ?Bearer $bearer,
		public \DateTimeImmutable $checkedAt,
	) {
	}
}
