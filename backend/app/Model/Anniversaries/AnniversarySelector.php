<?php

declare(strict_types=1);

namespace App\Model\Anniversaries;

use App\Model\Events\Section;
use App\Model\Selection\Candidate;
use App\Model\Selection\CardData;
use App\Model\Selection\DayAnalysis;


/**
 * At most one round anniversary a day, and only of a big event:
 * a) an event commemorated by a state holiday or significant day, or
 * b) an event with an article of its own – the primary article, or else the first linked article
 *    („Kongres USA“ is the primary article of the Declaration of Independence) – that is an event of a
 *    configured type, dated in the year of the event (so „Thirty Years' War“ is not the anniversary
 *    of a battle in 1626) and written about in enough Wikipedias (fewer needed for Czech events).
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
			$article = in_array($id, $commemoratedIds, true) ? null : $this->eventArticle($event);
			$reason = match (true) {
				in_array($id, $commemoratedIds, true) => 'událost státního svátku nebo významného dne',
				$article !== null => sprintf(
					'%s „%s“ je %s (%s), datum %d, %d jazykových verzí (hranice pro %s %d)',
					$article['title'] === $event->primaryTitle ? 'hlavní článek' : 'odkazovaný článek',
					$article['title'],
					$this->config->eventClasses[(string) $article['eventClass']] ?? $article['eventClass'],
					$article['eventClass'],
					$event->year,
					$article['sitelinks'],
					$event->section === Section::Czech ? 'Česko' : 'Svět',
					$this->config->minSitelinks($event->section),
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


	/**
	 * The article of the event itself: the primary one, or else the first linked one that is a big
	 * event dated in the year of the event with enough sitelinks.
	 * @return array{title: string, sitelinks: int, eventClass: string}|null
	 */
	private function eventArticle(CardData $event): ?array
	{
		$articles = [['title' => (string) $event->primaryTitle, 'sitelinks' => $event->primarySitelinks, 'eventClass' => $event->primaryEventClass, 'eventYears' => $event->primaryEventYears]];
		foreach ($event->links as $link) {
			$articles[] = ['title' => $link['title'], 'sitelinks' => $link['sitelinks'], 'eventClass' => $link['eventClass'] ?? null, 'eventYears' => $link['eventYears'] ?? []];
		}
		foreach ($articles as $article) {
			if ($article['eventClass'] !== null
				&& in_array($event->year, $article['eventYears'], true)
				&& ($article['sitelinks'] ?? 0) >= $this->config->minSitelinks($event->section)) {
				return ['title' => $article['title'], 'sitelinks' => (int) $article['sitelinks'], 'eventClass' => $article['eventClass']];
			}
		}
		return null;
	}
}
