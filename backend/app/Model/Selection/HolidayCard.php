<?php

declare(strict_types=1);

namespace App\Model\Selection;

use App\Model\Import\ArticleSummary;
use App\Model\StateDays\StateDay;


/**
 * A state holiday or significant day without an event of the day page: shown on its own card at the top.
 */
final readonly class HolidayCard
{
	public function __construct(
		public StateDay $stateDay,
		public ?ArticleSummary $summary,
	) {
	}
}
