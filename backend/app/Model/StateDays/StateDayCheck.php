<?php

declare(strict_types=1);

namespace App\Model\StateDays;

use App\Model\Import\ArticleSummary;
use App\Model\Selection\DayService;


/**
 * Checks every configured day against the stored data: an event fragment must match exactly one
 * active event (and that event must be first among the ten), an article must have a stored summary.
 */
final class StateDayCheck
{
	public function __construct(
		private readonly StateDayCalendar $calendar,
		private readonly DayService $dayService,
		private readonly StateDayArticleRepository $articles,
	) {
	}


	/**
	 * @return list<array{stateDay: StateDay, matches: int, first: bool, summary: ?ArticleSummary, ok: bool}>
	 */
	public function run(): array
	{
		$summaries = $this->articles->findByTitles(array_values(array_filter(array_map(fn(StateDay $d): ?string => $d->article, $this->calendar->all()))));
		$result = [];
		foreach ($this->calendar->all() as $stateDay) {
			$matches = 0;
			$first = false;
			if ($stateDay->hasEvent() && $stateDay->date !== null) {
				$analysis = $this->dayService->analyze($stateDay->date);
				$ids = $this->calendar->matchingEvents($stateDay, $analysis->events);
				$matches = count($ids);
				// commemorated events lead the ten, before the newest-first rest
				foreach ($this->dayService->day($stateDay->date)->cards as $card) {
					if ($card->stateDay === null) {
						break;
					}
					$first = $first || ($matches === 1 && $card->data->id === $ids[0] && $card->stateDay === $stateDay);
				}
			}
			$summary = $stateDay->article === null ? null : ($summaries[$stateDay->article] ?? null);
			$result[] = [
				'stateDay' => $stateDay,
				'matches' => $matches,
				'first' => $first,
				'summary' => $summary,
				'ok' => $stateDay->hasEvent() ? $matches === 1 && $first : $summary !== null,
			];
		}
		return $result;
	}
}
