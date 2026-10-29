<?php

declare(strict_types=1);

namespace App\Model\NameDays;

use App\Model\Import\SourceException;


interface NameArticleSource
{
	/**
	 * Finds the article about each name on the Wikipedia of the country; null when there is none
	 * (or only an article about a person, place or something else).
	 * @param list<string> $names
	 * @return array<string, NameArticle|null>
	 * @throws SourceException
	 */
	public function find(array $names, Country $country): array;
}
