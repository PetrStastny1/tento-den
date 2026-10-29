<?php

declare(strict_types=1);

namespace App\Model\StateDays;

use App\Model\Import\SourceException;


/**
 * Downloads the summaries of the articles of state days without an event (server only, console).
 */
final class StateDayArticleService
{
	public function __construct(
		private readonly StateDayCalendar $calendar,
		private readonly ArticleSummarySource $source,
		private readonly StateDayArticleRepository $articles,
	) {
	}


	/**
	 * @return array{saved: int, missing: list<string>}
	 * @throws SourceException
	 */
	public function refresh(): array
	{
		$titles = array_values(array_unique(array_filter(array_map(fn(StateDay $day): ?string => $day->article, $this->calendar->all()))));
		$result = ['saved' => 0, 'missing' => []];
		foreach ($titles as $title) {
			$summary = $this->source->fetch($title);
			if ($summary === null) {
				$result['missing'][] = $title;
				continue;
			}
			$this->articles->save($title, $summary);
			$result['saved']++;
		}
		return $result;
	}
}
