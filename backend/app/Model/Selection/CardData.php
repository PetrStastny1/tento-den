<?php

declare(strict_types=1);

namespace App\Model\Selection;

use App\Model\Events\EventStatus;
use App\Model\Events\MonthDay;
use App\Model\Events\Section;


/**
 * Everything a card needs about a stored event, as read from the database.
 */
final readonly class CardData
{
	/**
	 * @param list<array{title: string, sitelinks: int|null}> $links linked articles in text order
	 */
	public function __construct(
		public int $id,
		public MonthDay $day,
		public int $year,
		public Section $section,
		public string $text,
		public EventStatus $status,
		public string $sourceTitle,
		public int $sourceRevisionId,
		public \DateTimeImmutable $fetchedAt,
		public ?string $primaryTitle,
		public ?int $primaryRevisionId,
		public ?int $primarySitelinks,
		public array $links,
	) {
	}
}
