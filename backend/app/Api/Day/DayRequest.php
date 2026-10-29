<?php

declare(strict_types=1);

namespace App\Api\Day;

use App\Api\ApiException;
use App\Model\Events\MonthDay;


/**
 * Validated input of GET /api/day?date=MM-DD (date is optional, default today).
 */
final readonly class DayRequest
{
	private function __construct(
		public ?MonthDay $day,
	) {
	}


	/**
	 * @throws ApiException
	 */
	public static function fromQuery(mixed $date): self
	{
		if ($date === null) {
			return new self(null);
		}
		if (!is_string($date) || !preg_match('~^\d{2}-\d{2}$~', $date)) {
			throw ApiException::invalidRequest('Parametr date musí mít tvar MM-DD, např. 10-28.');
		}
		try {
			return new self(MonthDay::fromString($date));
		} catch (\InvalidArgumentException) {
			throw ApiException::invalidRequest("Datum $date neexistuje.");
		}
	}
}
