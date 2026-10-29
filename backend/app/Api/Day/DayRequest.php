<?php

declare(strict_types=1);

namespace App\Api\Day;

use App\Api\ApiException;
use App\Model\Events\MonthDay;


/**
 * Validated input of GET /api/day?date=MM-DD&year=YYYY (date is optional, default today; the year
 * is optional and only decides the movable holidays).
 */
final readonly class DayRequest
{
	private function __construct(
		public ?MonthDay $day,
		public ?int $year,
	) {
	}


	/**
	 * @throws ApiException
	 */
	public static function fromQuery(mixed $date, mixed $year = null): self
	{
		if ($year !== null && (!is_string($year) || !preg_match('~^\d{4}$~', $year) || (int) $year < 1583)) {
			throw ApiException::invalidRequest('Parametr year musí být rok gregoriánského kalendáře, např. 2026.');
		}
		$year = $year === null ? null : (int) $year;
		if ($date === null) {
			return new self(null, $year);
		}
		if (!is_string($date) || !preg_match('~^\d{2}-\d{2}$~', $date)) {
			throw ApiException::invalidRequest('Parametr date musí mít tvar MM-DD, např. 10-28.');
		}
		try {
			return new self(MonthDay::fromString($date), $year);
		} catch (\InvalidArgumentException) {
			throw ApiException::invalidRequest("Datum $date neexistuje.");
		}
	}
}
