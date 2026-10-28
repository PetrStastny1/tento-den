<?php

declare(strict_types=1);

namespace App\Model\Health;


/**
 * Output DTO of the health check.
 */
final readonly class HealthStatus implements \JsonSerializable
{
	public function __construct(
		public bool $healthy,
	) {
	}


	/**
	 * @return array{status: 'ok'|'error'}
	 */
	public function jsonSerialize(): array
	{
		return ['status' => $this->healthy ? 'ok' : 'error'];
	}
}
