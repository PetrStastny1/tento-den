<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Model\NameDays\Country;
use App\Model\NameDays\NameArticle;
use App\Model\NameDays\NameArticleSource;


/**
 * The listed names have an article about the name; the rest have none.
 */
final class FakeNameArticleSource implements NameArticleSource
{
	/**
	 * @param list<string> $names
	 */
	public function __construct(
		private readonly array $names,
	) {
	}


	public function find(array $names, Country $country): array
	{
		$result = [];
		foreach ($names as $name) {
			$result[$name] = in_array($name, $this->names, true) ? new NameArticle("$name (jméno)", 'Q' . crc32($name)) : null;
		}
		return $result;
	}
}
