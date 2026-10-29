<?php

declare(strict_types=1);

namespace App\Model\Anniversaries;


/**
 * How many years ago an event happened and whether that is a round anniversary:
 * 25, 50, 75, 100, 150, 200, 250, 300 and then every multiple of 100.
 * Years before Christ are negative and there is no year 0: from 45 BC to AD 2026 it is 2070 years.
 */
final class RoundAnniversary
{
	private const Steps = [25, 50, 75, 100, 150, 200, 250, 300];


	public static function yearsSince(int $eventYear, int $currentYear): int
	{
		if ($eventYear === 0) {
			throw new \InvalidArgumentException('There is no year 0');
		}
		return $eventYear < 0 ? $currentYear - $eventYear - 1 : $currentYear - $eventYear;
	}


	/**
	 * @return int|null the round number of years, null when it is not round
	 */
	public static function of(int $eventYear, int $currentYear): ?int
	{
		$years = self::yearsSince($eventYear, $currentYear);
		return in_array($years, self::Steps, true) || ($years > 300 && $years % 100 === 0) ? $years : null;
	}


	/**
	 * „100 let“ – every round number is 25 or more, so the plural is always „let“.
	 */
	public static function label(int $years): string
	{
		return "$years let";
	}
}
