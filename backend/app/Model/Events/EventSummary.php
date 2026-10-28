<?php

declare(strict_types=1);

namespace App\Model\Events;


/**
 * Read model of a stored event for listings (console output, later the API).
 */
final readonly class EventSummary
{
	public function __construct(
		public int $id,
		public int $month,
		public int $day,
		public Section $section,
		public int $year,
		public string $text,
		public EventStatus $status,
		public string $sourceTitle,
		public int $sourceRevisionId,
		public ?string $primaryTitle,
		public ?int $primaryRevisionId,
		public ?string $reviewNote,
	) {
	}
}
