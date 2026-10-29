<?php

declare(strict_types=1);

namespace App\Model\NameDays;


/**
 * One name of the "Svátky" section. Names of one list item share the line number; line 1 of a
 * country is its main entry (the civil calendar name on the page), further lines are other names.
 */
final readonly class ParsedNameDay
{
	public function __construct(
		public Country $country,
		public string $name,
		public int $line,
		public int $position,
		public ?string $note,
	) {
	}
}
