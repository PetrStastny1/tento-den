<?php

declare(strict_types=1);

namespace App\Model\Wikipedia;

use App\Model\Import\SourceException;


/**
 * The API answered with an error object, e.g. {"error":{"code":"no-such-entity","id":"Q1"}}.
 */
final class ActionApiErrorException extends SourceException
{
	/**
	 * @param array<string, mixed> $error
	 */
	public function __construct(
		public readonly string $errorCode,
		public readonly array $error,
	) {
		parent::__construct('Action API error: ' . $errorCode . ' – ' . (is_string($error['info'] ?? null) ? $error['info'] : ''));
	}
}
