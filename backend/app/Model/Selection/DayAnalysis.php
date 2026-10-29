<?php

declare(strict_types=1);

namespace App\Model\Selection;


/**
 * The inputs of the day's selection – used by the selection itself and by quality reports.
 */
final readonly class DayAnalysis
{
	/**
	 * @param list<Candidate> $candidates all active events of the day
	 * @param array<int, int> $rawSignals event id => sitelinks signal
	 * @param array<int, CardData> $events event id => data
	 */
	public function __construct(
		public array $candidates,
		public array $rawSignals,
		public array $events,
	) {
	}
}
