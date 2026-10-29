<?php

declare(strict_types=1);

namespace App\Model\Selection;


/**
 * What a day shows: holidays without an event on top, then the ten cards (a holiday card does not
 * count among the ten).
 */
final readonly class DayContent
{
	/**
	 * @param list<HolidayCard> $holidays
	 * @param list<Card> $cards commemorated events first, then newest first
	 */
	public function __construct(
		public array $holidays,
		public array $cards,
	) {
	}
}
