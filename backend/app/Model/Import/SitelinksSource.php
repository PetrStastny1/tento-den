<?php

declare(strict_types=1);

namespace App\Model\Import;


interface SitelinksSource
{
	/**
	 * Number of Wikipedia language editions with an article about each Wikidata item.
	 * @param list<string> $items Wikidata ids (Q…)
	 * @return array<string, int|null> item => count, null when the item does not exist
	 * @throws SourceException
	 */
	public function fetch(array $items): array;
}
