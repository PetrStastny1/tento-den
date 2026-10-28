<?php

declare(strict_types=1);

namespace App\Model\Import;

use App\Model\Events\MonthDay;


interface DayPageSource
{
	/**
	 * Current revision of the day page; the HTML must belong to exactly that revision.
	 * @throws SourceException
	 */
	public function fetch(MonthDay $day): DayPage;
}
