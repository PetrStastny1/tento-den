<?php

declare(strict_types=1);

namespace App\Model\Import;

use App\Model\Events\MonthDay;


final readonly class DayImportResult
{
	public function __construct(
		public MonthDay $day,
		public int $revisionId,
		public int $czechCount,
		public int $worldCount,
		public int $inserted,
		public int $updated,
		public int $unchanged,
		public int $missing,
		public int $review,
		public int $restored,
		public int $skipped,
		public int $nameDays = 0,
	) {
	}
}
