<?php

declare(strict_types=1);

namespace App\Model\Events;


final readonly class DayPageParseResult
{
	/**
	 * @param list<ParsedEvent> $events
	 * @param list<string> $skipped what was left out by an explicit rule, for the log
	 */
	public function __construct(
		public array $events,
		public array $skipped,
	) {
	}
}
