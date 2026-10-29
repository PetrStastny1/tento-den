<?php

declare(strict_types=1);

namespace App\Model\Selection;


/**
 * What a day shows: holidays without an event on top, then the ten cards (a holiday card does not
 * count among the ten); the year the round anniversaries were counted for (null: none counted).
 */
final readonly class DayContent
{
	/**
	 * @param list<HolidayCard> $holidays
	 * @param list<Card> $cards the round anniversary first, then commemorated events, then newest first
	 */
	public function __construct(
		public array $holidays,
		public array $cards,
		public ?int $anniversaryYear = null,
	) {
	}
}
