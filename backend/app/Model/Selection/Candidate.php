<?php

declare(strict_types=1);

namespace App\Model\Selection;

use App\Model\Events\Section;


/**
 * An active event prepared for the selection of the day's five cards.
 */
final readonly class Candidate
{
	public function __construct(
		public int $id,
		public Section $section,
		public int $year,
		public float $score,
		public ?string $boringReason,
	) {
	}


	/**
	 * 20 for 1901–2000, 1 for 1–100, -1 for 100 BC – 1 BC.
	 */
	public function century(): int
	{
		return $this->year > 0 ? intdiv($this->year - 1, 100) + 1 : -(intdiv(-$this->year - 1, 100) + 1);
	}
}
