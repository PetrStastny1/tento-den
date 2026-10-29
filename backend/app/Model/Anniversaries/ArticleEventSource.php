<?php

declare(strict_types=1);

namespace App\Model\Anniversaries;

use App\Model\Import\SourceException;


interface ArticleEventSource
{
	/**
	 * For each Wikidata item: the first configured event class it is an instance of (P31/P279*), or null,
	 * and the years of its date (point in time, start, inception; years BC negative, no year 0).
	 * @param list<string> $items
	 * @param list<string> $classes
	 * @return array<string, array{class: ?string, years: list<int>}>
	 * @throws SourceException
	 */
	public function classify(array $items, array $classes): array;
}
