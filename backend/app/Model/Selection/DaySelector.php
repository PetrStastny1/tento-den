<?php

declare(strict_types=1);

namespace App\Model\Selection;

use App\Model\Events\Section;


/**
 * Picks the five cards of a day (pure and deterministic – the same input gives the same five).
 * Each next card is the best remaining candidate by, in this order:
 * 1. not dull before dull,
 * 2. higher score,
 * 3. only at an equal score: a century not yet among the chosen cards,
 * 4. lower event id.
 * The two Czech cards (when the day has them) are chosen first by the same order.
 */
final class DaySelector
{
	public const Cards = 5;
	public const MinCzech = 2;


	/**
	 * @param list<Candidate> $candidates
	 * @return list<Candidate> in display order: chronological
	 */
	public function select(array $candidates): array
	{
		$chosen = [];
		$czech = array_values(array_filter($candidates, fn(Candidate $c): bool => $c->section === Section::Czech));
		$this->fill($chosen, $czech, self::MinCzech);
		$this->fill($chosen, $candidates, self::Cards);

		usort($chosen, fn(Candidate $a, Candidate $b): int => [$a->year, $a->id] <=> [$b->year, $b->id]);
		return $chosen;
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
