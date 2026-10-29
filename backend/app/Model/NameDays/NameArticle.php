<?php

declare(strict_types=1);

namespace App\Model\NameDays;


/**
 * The article about a name (verified via Wikidata: an instance of a given name) and its Wikidata item.
 */
final readonly class NameArticle
{
	/**
	 * @param list<string> $relatedItems items the name is said to be the same as (P460) or the name for the other gender (P1560)
	 */
	public function __construct(
		public string $title,
		public string $wikidataItem,
		public array $relatedItems = [],
	) {
	}
}
