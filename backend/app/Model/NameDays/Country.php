<?php

declare(strict_types=1);

namespace App\Model\NameDays;


/**
 * Countries whose name days the day page lists (ISO 3166 codes, like the event sections).
 */
enum Country: string
{
	case Czech = 'cz';
	case Slovak = 'sk';


	/**
	 * The Wikipedia of the country: its calendar, the meaning of names and the bearers' articles.
	 */
	public function language(): string
	{
		return match ($this) {
			self::Czech => 'cs',
			self::Slovak => 'sk',
		};
	}


	public function label(): string
	{
		return match ($this) {
			self::Czech => 'Česko',
			self::Slovak => 'Slovensko',
		};
	}
}
