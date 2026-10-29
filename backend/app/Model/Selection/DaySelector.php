<?php

declare(strict_types=1);

namespace App\Model\Selection;

use App\Model\Events\Section;


/**
 * Picks the five cards of a day (pure and deterministic – the same input gives the same five):
 * - at least two Czech events when the day has them,
 * - dull events only when nothing else is left,
 * - no two cards from the same century where it can be avoided,
 * - otherwise the highest score; ties broken by event id.
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
		usort($candidates, fn(Candidate $a, Candidate $b): int => [$a->boringReason !== null, -$a->score, $a->id] <=> [$b->boringReason !== null, -$b->score, $b->id]);

		$chosen = [];
		$czech = array_values(array_filter($candidates, fn(Candidate $c): bool => $c->section === Section::Czech));
		$this->fill($chosen, $czech, self::MinCzech);
		$this->fill($chosen, $candidates, self::Cards);

		usort($chosen, fn(Candidate $a, Candidate $b): int => [$a->year, $a->id] <=> [$b->year, $b->id]);
		return $chosen;
	}


	/**
	 * Adds candidates up to $target cards: first only those from unused centuries
	 * (and not dull), then anything that is left.
	 * @param list<Candidate> $chosen
	 * @param list<Candidate> $pool sorted by preference
	 */
	private function fill(array &$chosen, array $pool, int $target): void
	{
		foreach ([true, false] as $strict) {
			foreach ($pool as $candidate) {
				if (count($chosen) >= $target) {
					return;
				}
				if (in_array($candidate, $chosen, true)) {
					continue;
				}
				$centuries = array_map(fn(Candidate $c): int => $c->century(), $chosen);
				if ($strict && ($candidate->boringReason !== null || in_array($candidate->century(), $centuries, true))) {
					continue;
				}
				$chosen[] = $candidate;
			}
		}
	}
}
