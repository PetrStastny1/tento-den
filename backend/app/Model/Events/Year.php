<?php

declare(strict_types=1);

namespace App\Model\Events;


/**
 * Historical year numbering as used by Wikipedia (no year 0):
 * 1918 = 1918 n. l., -1 = 1 př. n. l., -45 = 45 př. n. l.
 */
final class Year
{
	/**
	 * @throws \InvalidArgumentException
	 */
	public static function fromText(int $number, bool $beforeChrist): int
	{
		if ($number === 0) {
			throw new \InvalidArgumentException('Year 0 does not exist');
		}
		return $beforeChrist ? -$number : $number;
	}


	public static function format(int $year): string
	{
		return $year < 0 ? -$year . ' př. n. l.' : (string) $year;
	}
}
