<?php

declare(strict_types=1);

namespace App\Model\NameDays;

use App\Model\Import\SourceException;


interface BearerSource
{
	/**
	 * The best known person with this given name (Wikidata item of the name) born on the territory of
	 * today's country, who has an article on the Wikipedia of the country.
	 * @param list<string> $excluded Wikidata items of people excluded after review
	 * @throws SourceException
	 */
	public function find(string $nameItem, string $name, Country $country, array $excluded = []): ?Bearer;
}
