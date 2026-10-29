<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Model\NameDays\Bearer;
use App\Model\NameDays\BearerSource;
use App\Model\NameDays\Country;


final class FakeBearerSource implements BearerSource
{
	/**
	 * @param array<string, string> $bearers name => bearer's article title
	 */
	public function __construct(
		private readonly array $bearers,
	) {
	}


	public function find(string $nameItem, string $name, Country $country, array $excluded = []): ?Bearer
	{
		$article = $this->bearers[$name] ?? null;
		return $article === null || in_array('Q42', $excluded, true) ? null : new Bearer('Q42', $article, $article, 99, 'voják');
	}
}
