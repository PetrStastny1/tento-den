<?php

declare(strict_types=1);

namespace App\Model\Selection;

use App\Core\Clock\Clock;
use App\Model\Events\MonthDay;
use App\Model\Events\PrimaryArticleSelector;
use App\Model\Events\Section;
use App\Model\StateDays\StateDay;
use App\Model\StateDays\StateDayArticleRepository;
use App\Model\StateDays\StateDayCalendar;


/**
 * The ten cards of a day and single cards, computed from stored events on request
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
		private readonly StateDayCalendar $stateDays,
		private readonly StateDayArticleRepository $stateDayArticles,
	) {
	}


	/**
	 * "Today" is the same for everybody: the date in Europe/Prague.
	 */
	public function today(): MonthDay
	{
		$now = $this->now();
		return new MonthDay((int) $now->format('n'), (int) $now->format('j'));
	}


	public function currentYear(): int
	{
		return (int) $this->now()->format('Y');
	}


	/**
	 * Holidays without an event on top, then the ten cards: events commemorated by a state day first,
	 * the rest newest first. Movable holidays (Easter) need the year; without it they are left out.
	 */
	public function day(MonthDay $day, ?int $year = null): DayContent
	{
		$analysis = $this->analyze($day);
		$forced = $commemorated = $standalone = [];
		foreach ($this->stateDays->forDay($day, $year) as $stateDay) {
			if (!$stateDay->hasEvent()) {
				$standalone[] = $stateDay;
				continue;
			}
			$ids = $this->stateDays->matchingEvents($stateDay, $analysis->events);
			// only an unambiguous match counts; anything else is an error in the quality report
			if (count($ids) === 1) {
				$forced[] = $ids[0];
				$commemorated[$ids[0]] = $stateDay;
			}
		}

		$summaries = $this->stateDayArticles->findByTitles(array_map(fn(StateDay $s): string => (string) $s->article, $standalone));
		return new DayContent(
			array_map(fn(StateDay $s): HolidayCard => new HolidayCard($s, $summaries[(string) $s->article] ?? null), $standalone),
			array_map(
				fn(Candidate $candidate): Card => $this->toCard($analysis->events[$candidate->id], $commemorated[$candidate->id] ?? null),
				$this->selector->select($analysis->candidates, $forced),
			),
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


	/**
	 * A single card, labelled like on its day when a (fixed-date) state day commemorates it.
	 */
	public function card(int $id): ?Card
	{
		$event = $this->cards->findById($id);
		if ($event === null) {
			return null;
		}
		$analysis = null;
		foreach ($this->stateDays->forDay($event->day, null) as $stateDay) {
			$analysis ??= $this->analyze($event->day);
			if ($this->stateDays->matchingEvents($stateDay, $analysis->events) === [$id]) {
				return $this->toCard($event, $stateDay);
			}
		}
		return $this->toCard($event);
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


	private function toCard(CardData $event, ?StateDay $stateDay = null): Card
	{
		return new Card($event, $this->sensitivity->isSensitive($event->text), $stateDay);
	}


	private function now(): \DateTimeImmutable
	{
		return $this->clock->now()->setTimezone(new \DateTimeZone(self::TimeZone));
	}
}
