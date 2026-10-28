<?php

declare(strict_types=1);

namespace App\Model\Events;


/**
 * Changes within one section and year that the import does not decide: incoming events
 * are stored as replacements, the stored candidates are kept as originals, all in review.
 */
final readonly class ReviewCase
{
	/**
	 * @param list<IncomingEvent> $incoming
	 * @param array<int, float> $candidates stored event id => best similarity to any incoming event
	 */
	public function __construct(
		public array $incoming,
		public array $candidates,
	) {
	}
}
