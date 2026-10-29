<?php

declare(strict_types=1);

namespace App\Model\Selection;

use App\Model\Anniversaries\Anniversary;
use App\Model\StateDays\StateDay;


final readonly class Card
{
	/**
	 * @param StateDay|null $stateDay the state holiday or significant day this event is commemorated by
	 */
	public function __construct(
		public CardData $data,
		public bool $sensitive,
		public ?StateDay $stateDay = null,
		public ?Anniversary $anniversary = null,
	) {
	}
}
