<?php

declare(strict_types=1);

namespace App\Model\Events;


enum Section: string
{
	case Czech = 'cz';
	case World = 'world';


	/**
	 * Maps a subsection heading of "Události" on a cs.wikipedia day page.
	 */
	public static function fromHeading(string $heading): ?self
	{
		return match (trim($heading)) {
			'Česko' => self::Czech,
			'Svět' => self::World,
			default => null,
		};
	}
}
