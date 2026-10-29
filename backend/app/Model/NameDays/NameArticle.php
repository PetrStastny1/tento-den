<?php

declare(strict_types=1);

namespace App\Model\NameDays;


/**
 * The article about a name (verified via Wikidata: an instance of a given name) and its Wikidata item.
 */
final readonly class NameArticle
{
	public function __construct(
		public string $title,
		public string $wikidataItem,
	) {
	}
}
