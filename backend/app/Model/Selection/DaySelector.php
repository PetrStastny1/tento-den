<?php

declare(strict_types=1);

namespace App\Model\Selection;

use App\Model\Events\Section;


/**
 * Picks the ten cards of a day (pure and deterministic – the same input gives the same ten).
 * Forced events (the round anniversary, events a state holiday or significant day commemorates) are
 * always chosen, come first and count towards the quotas.
 * A good candidate is not dull, not excluded and not a second event of a chosen type. The places go,
 * in this order, to:
 * 1. three Czech events (when the day has them), those before 1800 with at least a modest score first,
 * 2. four events before 1800,
 * 3. anything else, with at most four events from 1900 on,
 * 4. newer events over that limit,
 * 5. the rest: dull events, then second events of a type, excluded events last.
 * Steps 1–4 take good candidates only, so no quota can bring a poor event back. A day always gets
 * ten cards when it has that many events. Within a step the order is by score (with a bonus for
 * remarkable events), then a century not yet chosen, then the lower id.
 */
final class DaySelector
{
	public const Cards = 10;
	public const MinCzech = 3;
	public const MinBefore1800 = 4;
	public const MaxModern = 4;

	/** The Czech preference for old events takes only these; weaker old Czech events compete like any other. */
	public const MinCzechOldScore = 0.3;

	/** Normalized scores are 0..1, so this outweighs a difference in sitelinks, not everything. */
	private const RemarkableBonus = 0.3;


	/**
	 * @param list<Candidate> $candidates
	 * @param list<int> $forcedIds the round anniversary, then events commemorated by a state day
	 * @return list<Candidate> in display order: forced first, then newest first
	 */
	public function select(array $candidates, array $forcedIds = []): array
	{
		$forced = [];
		foreach ($forcedIds as $id) {
			foreach ($candidates as $candidate) {
				if ($candidate->id === $id && !in_array($candidate, $forced, true)) {
					$forced[] = $candidate;
				}
			}
		}

		$chosen = $forced;
		$isCzech = fn(Candidate $c): bool => $c->section === Section::Czech;
		$isBefore1800 = fn(Candidate $c): bool => $c->isBefore1800();
		$isModern = fn(Candidate $c): bool => $c->isModern();
		$czechDone = fn(array $chosen): bool => count(array_filter($chosen, $isCzech)) >= self::MinCzech;
		$oldDone = fn(array $chosen): bool => count(array_filter($chosen, $isBefore1800)) >= self::MinBefore1800;
		$never = fn(array $chosen): bool => false;

		$this->fill(
			$chosen,
			$candidates,
			fn($c, $chosen) => $isCzech($c) && $isBefore1800($c) && $c->score >= self::MinCzechOldScore && $this->isGood($c, $chosen),
			$czechDone,
		);
		$this->fill($chosen, $candidates, fn($c, $chosen) => $isCzech($c) && $this->isGood($c, $chosen), $czechDone);
		$this->fill($chosen, $candidates, fn($c, $chosen) => $isBefore1800($c) && $this->isGood($c, $chosen), $oldDone);
		$this->fill(
			$chosen,
			$candidates,
			fn($c, $chosen) => $this->isGood($c, $chosen) && (!$isModern($c) || count(array_filter($chosen, $isModern)) < self::MaxModern),
			$never,
		);
		$this->fill($chosen, $candidates, $this->isGood(...), $never);
		$this->fill($chosen, $candidates, fn($c, $chosen) => true, $never);

		$rest = array_values(array_filter($chosen, fn(Candidate $c): bool => !in_array($c, $forced, true)));
		usort($rest, fn(Candidate $a, Candidate $b): int => [$b->year, $a->id] <=> [$a->year, $b->id]);
		return [...$forced, ...$rest];
	}


	/**
	 * Adds the best eligible candidate until the day is full, the goal is met or nobody is eligible.
	 * @param list<Candidate> $chosen
	 * @param list<Candidate> $candidates
	 * @param \Closure(Candidate, list<Candidate>): bool $eligible
	 * @param \Closure(list<Candidate>): bool $done
	 */
	private function fill(array &$chosen, array $candidates, \Closure $eligible, \Closure $done): void
	{
		while (count($chosen) < self::Cards && !$done($chosen)) {
			$pool = array_values(array_filter(
				$candidates,
				fn(Candidate $c): bool => !in_array($c, $chosen, true) && $eligible($c, $chosen),
			));
			if ($pool === []) {
				return;
			}
			$centuries = array_map(fn(Candidate $c): int => $c->century(), $chosen);
			$key = fn(Candidate $c): array => [
				$c->excludedReason !== null,
				$this->isDuplicate($c, $chosen),
				$c->boringReason !== null,
				-$this->rank($c),
				in_array($c->century(), $centuries, true),
				$c->id,
			];
			usort($pool, fn(Candidate $a, Candidate $b): int => $key($a) <=> $key($b));
			$chosen[] = $pool[0];
		}
	}


	/**
	 * @param list<Candidate> $chosen
	 */
	private function isGood(Candidate $candidate, array $chosen): bool
	{
		return $candidate->boringReason === null && $candidate->excludedReason === null && !$this->isDuplicate($candidate, $chosen);
	}


	/**
	 * @param list<Candidate> $chosen
	 */
	private function isDuplicate(Candidate $candidate, array $chosen): bool
	{
		return $candidate->type !== null && in_array($candidate->type, array_map(fn(Candidate $c): ?string => $c->type, $chosen), true);
	}


	private function rank(Candidate $candidate): float
	{
		return $candidate->score + ($candidate->remarkable ? self::RemarkableBonus : 0.0);
	}
}
