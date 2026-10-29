<?php

declare(strict_types=1);

namespace App\Model\Events;


/**
 * A day of the year without a year ("10-28"). February 29 is always a valid anniversary.
 */
final readonly class MonthDay
{
	private const MonthNames = [
		1 => 'leden', 2 => 'únor', 3 => 'březen', 4 => 'duben', 5 => 'květen', 6 => 'červen',
		7 => 'červenec', 8 => 'srpen', 9 => 'září', 10 => 'říjen', 11 => 'listopad', 12 => 'prosinec',
	];

	private const SlovakMonthNames = [
		1 => 'január', 2 => 'február', 3 => 'marec', 4 => 'apríl', 5 => 'máj', 6 => 'jún',
		7 => 'júl', 8 => 'august', 9 => 'september', 10 => 'október', 11 => 'november', 12 => 'december',
	];

	private const DaysInMonth = [1 => 31, 2 => 29, 3 => 31, 4 => 30, 5 => 31, 6 => 30, 7 => 31, 8 => 31, 9 => 30, 10 => 31, 11 => 30, 12 => 31];


	public function __construct(
		public int $month,
		public int $day,
	) {
		if (!isset(self::DaysInMonth[$month]) || $day < 1 || $day > self::DaysInMonth[$month]) {
			throw new \InvalidArgumentException("Invalid day of year: $month-$day");
		}
	}


	/**
	 * @throws \InvalidArgumentException
	 */
	public static function fromString(string $value): self
	{
		if (!preg_match('~^(\d{1,2})-(\d{1,2})$~', $value, $m)) {
			throw new \InvalidArgumentException("Expected MM-DD, got '$value'");
		}
		return new self((int) $m[1], (int) $m[2]);
	}


	/**
	 * All 366 days in calendar order.
	 * @return list<self>
	 */
	public static function all(): array
	{
		$days = [];
		foreach (self::DaysInMonth as $month => $count) {
			for ($day = 1; $day <= $count; $day++) {
				$days[] = new self($month, $day);
			}
		}
		return $days;
	}


	/**
	 * Title of the day page on cs.wikipedia ("28. říjen") or sk.wikipedia ("28. október").
	 */
	public function dayPageTitle(string $language = 'cs'): string
	{
		return $this->day . '. ' . ($language === 'sk' ? self::SlovakMonthNames : self::MonthNames)[$this->month];
	}


	public function equals(self $other): bool
	{
		return $this->month === $other->month && $this->day === $other->day;
	}


	public function format(): string
	{
		return sprintf('%02d-%02d', $this->month, $this->day);
	}
}
