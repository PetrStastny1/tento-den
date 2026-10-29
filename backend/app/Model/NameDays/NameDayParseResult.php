<?php

declare(strict_types=1);

namespace App\Model\NameDays;


final readonly class NameDayParseResult
{
	/**
	 * @param list<ParsedNameDay> $nameDays
	 * @param list<string> $skipped items that are not name days (holidays, saints, notes), for the log
	 */
	public function __construct(
		public array $nameDays,
		public array $skipped,
	) {
	}
}
