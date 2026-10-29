<?php

declare(strict_types=1);

namespace App\Model\Anniversaries;

use App\Model\Selection\Candidate;
use App\Model\Selection\DayAnalysis;


/**
 * At most one round anniversary a day, and only of a big event:
 * a) an event commemorated by a state holiday or significant day, or
 * b) an event whose primary article is itself an event of a configured type, dated in the year of
 *    the event (so „Thirty Years' War“ is not the anniversary of a battle in 1626) and written about
 *    in at least minSitelinks Wikipedias.
 * Excluded after human review: never. Of several: the higher anniversary, then the higher score.
 */
final class AnniversarySelector
{
	public function __construct(
		private readonly AnniversaryConfig $config,
		private readonly AnniversaryExclusions $exclusions,
	) {
	}


	/**
	 * @param list<int> $commemoratedIds events commemorated by a state day on this day
	 */
	public function choose(DayAnalysis $analysis, array $commemoratedIds, int $year): ?Anniversary
	{
		$scores = [];
		foreach ($analysis->candidates as $candidate) {
			$scores[$candidate->id] = $candidate->score;
		}

		$best = null;
		foreach ($analysis->events as $id => $event) {
			$years = RoundAnniversary::of($event->year, $year);
			if ($years === null || $this->exclusions->isExcluded($event)) {
				continue;
			}
			$reason = match (true) {
				in_array($id, $commemoratedIds, true) => 'událost státního svátku nebo významného dne',
				$event->primaryEventClass !== null
					&& in_array($event->year, $event->primaryEventYears, true)
					&& ($event->primarySitelinks ?? 0) >= $this->config->minSitelinks => sprintf(
						'hlavní článek „%s“ je %s (%s), datum %d, %d jazykových verzí',
						$event->primaryTitle,
						$this->config->eventClasses[$event->primaryEventClass] ?? $event->primaryEventClass,
						$event->primaryEventClass,
						$event->year,
						$event->primarySitelinks,
					),
				default => null,
			};
			if ($reason === null) {
				continue;
			}
			$key = [$years, $scores[$id] ?? 0.0, -$id];
			if ($best === null || $key > $best['key']) {
				$best = ['key' => $key, 'anniversary' => new Anniversary($id, $years, $reason)];
			}
		}
		return $best['anniversary'] ?? null;
	}
}
