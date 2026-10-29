<?php

declare(strict_types=1);

namespace App\Model\NameDays;

use App\Model\Import\ArticleSummary;


/**
 * What is known about a name in one mode: the introduction of its article and a known bearer.
 */
final readonly class NameInfo
{
	/**
	 * @param list<string> $relatedItems Wikidata P460 and P1560 of the name item
	 */
	public function __construct(
		public string $name,
		public Country $country,
		public ?string $wikidataItem,
		public ?ArticleSummary $meaning,
		public ?Bearer $bearer,
		public \DateTimeImmutable $checkedAt,
		public array $relatedItems = [],
	) {
	}
}
