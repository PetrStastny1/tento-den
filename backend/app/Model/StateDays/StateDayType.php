<?php

declare(strict_types=1);

namespace App\Model\StateDays;


/**
 * The three groups of Act No. 245/2000 Coll.
 */
enum StateDayType: string
{
	case State = 'state';
	case Other = 'other';
	case Significant = 'significant';


	public function label(): string
	{
		return match ($this) {
			self::State => 'Státní svátek',
			self::Other => 'Ostatní svátek',
			self::Significant => 'Významný den',
		};
	}
}
