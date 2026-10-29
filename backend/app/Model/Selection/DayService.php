<?php

declare(strict_types=1);

namespace App\Model\Selection;

use App\Core\Clock\Clock;
use App\Model\Events\MonthDay;
use App\Model\Events\PrimaryArticleSelector;
use App\Model\Events\Section;


/**
 * The five cards of a day and single cards, computed from stored events on request
 * (deterministic, so no stored selection is needed).
 */
final class DayService
{
	private const TimeZone = 'Europe/Prague';


	public function __construct(
		private readonly CardRepository $cards,
		private readonly EventScorer $scorer,
		private readonly BoringFilter $boringFilter,
		private readonly SensitivityClassifier $sensitivity,
		private readonly DaySelector $selector,
		private readonly PrimaryArticleSelector $primaryArticles,
		private readonly Clock $clock,
	) {
	}


	/**
	 * "Today" is the same for everybody: the date in Europe/Prague.
	 */
	public function today(): MonthDay
	{
		$now = $this->clock->now()->setTimezone(new \DateTimeZone(self::TimeZone));
		return new MonthDay((int) $now->format('n'), (int) $now->format('j'));
	}


	/**
	 * @return list<Card> chronological
	 */
	public function cardsForDay(MonthDay $day): array
	{
		$analysis = $this->analyze($day);
		return array_map(
			fn(Candidate $candidate): Card => $this->toCard($analysis->events[$candidate->id]),
			$this->selector->select($analysis->candidates),
		);
	}


	public function analyze(MonthDay $day): DayAnalysis
	{
		$events = [];
		foreach ($this->cards->findActiveByDay($day) as $event) {
			$events[$event->id] = $event;
		}

		$candidates = $rawSignals = [];
		foreach ([Section::Czech, Section::World] as $section) {
			$raw = [];
			foreach ($events as $id => $event) {
				if ($event->section === $section) {
					$raw[$id] = $this->rawSignal($event);
				}
			}
			$rawSignals += $raw;
			foreach ($this->scorer->normalize($raw) as $id => $score) {
				$candidates[] = new Candidate($id, $section, $events[$id]->year, $score, $this->boringFilter->reason($events[$id]->text));
			}
		}
		return new DayAnalysis($candidates, $rawSignals, $events);
	}


	public function card(int $id): ?Card
	{
		$event = $this->cards->findById($id);
		return $event === null ? null : $this->toCard($event);
	}


	private function rawSignal(CardData $event): int
	{
		$others = [];
		foreach ($event->links as $link) {
			if ($link['title'] !== $event->primaryTitle && !$this->primaryArticles->isGeneric($link['title'])) {
				$others[] = $link['sitelinks'];
			}
		}
		$primaryIsGeneric = $event->primaryTitle !== null && $this->primaryArticles->isGeneric($event->primaryTitle);
		return $this->scorer->rawSignal($event->primarySitelinks, $primaryIsGeneric, $others);
	}


	private function toCard(CardData $event): Card
	{
		return new Card($event, $this->sensitivity->isSensitive($event->text));
	}
}
