<?php

declare(strict_types=1);

namespace App\Model\Selection;


/**
 * Conservative filter of dull events (sport results, shop openings, software releases…).
 * A dull event is only moved behind the others, never deleted. The rules are narrow instead of
 * having exceptions: an exemption by sitelinks was tried and removed, because the primary article
 * is often the backdrop (a match in Sweden "had" the 296 languages of "Švédsko").
 */
final class BoringFilter
{
	public function __construct(
		private readonly TextRules $rules,
	) {
	}


	public function reason(string $text): ?string
	{
		return $this->rules->match($text);
	}
}
