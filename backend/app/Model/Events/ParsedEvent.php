<?php

declare(strict_types=1);

namespace App\Model\Events;


/**
 * One event as read from the day page, before article resolution.
 */
final readonly class ParsedEvent
{
	/**
	 * @param list<string> $linkTitles linked article titles in text order (years and dates excluded)
	 */
	public function __construct(
		public Section $section,
		public int $year,
		public string $text,
		public int $position,
		public array $linkTitles,
	) {
	}
}
