<?php

declare(strict_types=1);

namespace App\Model\Selection;

use App\Model\Events\Section;


/**
 * Picks the ten cards of a day (pure and deterministic – the same input gives the same ten).
 * Events a state holiday or significant day commemorates are always chosen and come first.
 * Each next card is the best remaining candidate by, in this order:
 * 1. not dull before dull,
 * 2. higher score,
 * 3. only at an equal score: a century not yet among the chosen cards,
 * 4. lower event id.
 * Three Czech cards (when the day has them) are guaranteed, chosen by the same order.
 * No limit by age: anything from antiquity to the present can be chosen.
 */
final class DaySelector
{
	public const Cards = 10;
	public const MinCzech = 3;


	/**
	 * @param list<Candidate> $candidates
	 * @param list<int> $forcedIds events commemorated by a state day, in the order of the act
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
		$missingCzech = max(0, self::MinCzech - count(array_filter($chosen, $isCzech)));
		$this->fill($chosen, array_values(array_filter($candidates, $isCzech)), count($chosen) + $missingCzech);
		$this->fill($chosen, $candidates, self::Cards);

		$rest = array_values(array_filter($chosen, fn(Candidate $c): bool => !in_array($c, $forced, true)));
		usort($rest, fn(Candidate $a, Candidate $b): int => [$b->year, $a->id] <=> [$a->year, $b->id]);
		return [...$forced, ...$rest];
	}


	/**
	 * @param list<Candidate> $chosen
	 * @param list<Candidate> $pool
	 */
	private function fill(array &$chosen, array $pool, int $target): void
	{
		$remaining = array_values(array_filter($pool, fn(Candidate $c): bool => !in_array($c, $chosen, true)));
		while (count($chosen) < $target && $remaining !== []) {
			$centuries = array_map(fn(Candidate $c): int => $c->century(), $chosen);
			$key = fn(Candidate $c): array => [$c->boringReason !== null, -$c->score, in_array($c->century(), $centuries, true), $c->id];
			usort($remaining, fn(Candidate $a, Candidate $b): int => $key($a) <=> $key($b));
			$chosen[] = array_shift($remaining);
		}
	}
}
