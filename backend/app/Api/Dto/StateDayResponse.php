<?php

declare(strict_types=1);

namespace App\Api\Dto;

use App\Model\StateDays\StateDay;


/**
 * The official name and type of a day from Act No. 245/2000 Coll.
 */
final readonly class StateDayResponse implements \JsonSerializable
{
	public const Law = 'zákon č. 245/2000 Sb.';


	public function __construct(
		private StateDay $stateDay,
	) {
	}


	/**
	 * @return array{id: string, name: string, type: string, typeLabel: string, law: string, lawUrl: string}
	 */
	public function jsonSerialize(): array
	{
		return [
			'id' => $this->stateDay->id,
			'name' => $this->stateDay->name,
			'type' => $this->stateDay->type->value,
			'typeLabel' => $this->stateDay->type->label(),
			'law' => self::Law,
			'lawUrl' => $this->stateDay->source,
		];
	}
}
