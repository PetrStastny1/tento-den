<?php

declare(strict_types=1);

namespace App\Model\StateDays;

use App\Model\Events\MonthDay;


/**
 * One day of Act No. 245/2000 Coll.: a fixed date or a number of days from Easter Sunday. It either
 * commemorates an event of the day page (identified by year and text fragment) or is presented by
 * the summary of a Wikipedia article.
 */
final readonly class StateDay
{
	public string $id;


	public function __construct(
		public string $name,
		public StateDayType $type,
		public string $source,
		public ?MonthDay $date = null,
		public ?int $easterOffset = null,
		public ?int $eventYear = null,
		public ?string $eventText = null,
		public ?string $article = null,
	) {
		if (($date === null) === ($easterOffset === null)) {
			throw new \InvalidArgumentException("„{$name}“: exactly one of date and easter is required");
		}
		if (($eventText === null) === ($article === null) || ($eventText === null) !== ($eventYear === null)) {
			throw new \InvalidArgumentException("„{$name}“: exactly one of event (year and text) and article is required");
		}
		if ($easterOffset !== null && $eventText !== null) {
			throw new \InvalidArgumentException("„{$name}“: a movable holiday cannot commemorate a dated event");
		}
		$this->id = self::slug($name);
	}


	/**
	 * Movable holidays need the year; viewing a day and month without a year leaves them out.
	 */
	public function occursOn(MonthDay $day, ?int $year): bool
	{
		if ($this->date !== null) {
			return $this->date->equals($day);
		}
		return $year !== null && $this->dateIn($year)->equals($day);
	}


	public function dateIn(int $year): MonthDay
	{
		if ($this->date !== null) {
			return $this->date;
		}
		$date = Easter::sunday($year)->modify(sprintf('%+d days', (int) $this->easterOffset));
		return new MonthDay((int) $date->format('n'), (int) $date->format('j'));
	}


	/**
	 * "Den české vlajky" → "den-ceske-vlajky" (without the intl extension, the names are Czech).
	 */
	private static function slug(string $name): string
	{
		$ascii = strtr(mb_strtolower($name), [
			'á' => 'a', 'č' => 'c', 'ď' => 'd', 'é' => 'e', 'ě' => 'e', 'í' => 'i', 'ň' => 'n', 'ó' => 'o',
			'ř' => 'r', 'š' => 's', 'ť' => 't', 'ú' => 'u', 'ů' => 'u', 'ý' => 'y', 'ž' => 'z',
		]);
		return trim((string) preg_replace('~[^a-z0-9]+~', '-', $ascii), '-');
	}


	public function hasEvent(): bool
	{
		return $this->eventText !== null;
	}
}
