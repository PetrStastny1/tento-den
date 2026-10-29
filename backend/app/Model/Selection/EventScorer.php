<?php

declare(strict_types=1);

namespace App\Model\Selection;


/**
 * Importance signal of an event from the number of Wikipedia language editions (sitelinks).
 * Pure functions; normalization is done separately for each day and section, so Czech events
 * are never compared with world ones.
 */
final class EventScorer
{
	/**
	 * Sitelinks of the primary article; when there is none (or it is generic), the highest
	 * sitelinks among the event's non-generic articles; 0 without any signal.
	 * @param list<int|null> $otherNonGenericSitelinks
	 */
	public function rawSignal(?int $primarySitelinks, bool $primaryIsGeneric, array $otherNonGenericSitelinks): int
	{
		if ($primarySitelinks !== null && !$primaryIsGeneric) {
			return $primarySitelinks;
		}
		$known = array_filter($otherNonGenericSitelinks, fn(?int $count): bool => $count !== null);
		return $known === [] ? 0 : max($known);
	}


	/**
	 * log(1 + signal) / log(1 + max) → 0..1 within one group (day + section).
	 * @param array<int, int> $rawSignals event id => raw signal
	 * @return array<int, float> event id => normalized score
	 */
	public function normalize(array $rawSignals): array
	{
		$max = $rawSignals === [] ? 0 : max($rawSignals);
		if ($max <= 0) {
			return array_map(fn(): float => 0.0, $rawSignals);
		}
		return array_map(fn(int $signal): float => log(1 + max($signal, 0)) / log(1 + $max), $rawSignals);
	}
}
