<?php

declare(strict_types=1);

namespace App\Api\Event;

use App\Api\ApiException;


/**
 * Validated input of GET /api/events/{id}.
 */
final readonly class EventRequest
{
	private function __construct(
		public int $id,
	) {
	}


	/**
	 * @throws ApiException
	 */
	public static function fromRoute(mixed $id): self
	{
		if (!is_string($id) || !preg_match('~^[1-9]\d{0,8}$~', $id)) {
			throw ApiException::notFound('Událost nenalezena.');
		}
		return new self((int) $id);
	}
}
