<?php

declare(strict_types=1);

namespace App\Model\Import;


/**
 * Parsoid HTML of one exact revision of a day page.
 */
final readonly class DayPage
{
	public function __construct(
		public string $title,
		public int $revisionId,
		public string $html,
	) {
	}
}
