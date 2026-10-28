<?php

declare(strict_types=1);

namespace App\Model\Events;


/**
 * An event already in the database, as far as matching needs it.
 */
final readonly class StoredEvent
{
	public function __construct(
		public int $id,
		public Section $section,
		public int $year,
		public string $text,
		public string $textHash,
		public EventStatus $status,
		public ?int $primaryPageId,
	) {
	}
}
