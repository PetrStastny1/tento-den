<?php

declare(strict_types=1);

namespace App\Model\NameDays;


/**
 * A known person with the given name: a Wikidata item and the article on the Wikipedia of the mode.
 */
final readonly class Bearer
{
	public function __construct(
		public string $wikidataItem,
		public string $name,
		public string $article,
		public int $sitelinks,
		public ?string $occupations = null,
	) {
	}
}
