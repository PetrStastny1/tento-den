<?php

declare(strict_types=1);

namespace App\Model\Events;


/**
 * An incoming event that may be an edit of one of the candidates – the import does not guess.
 */
final readonly class ReviewCase
{
	/**
	 * @param array<int, float> $candidates stored event id => similarity
	 */
	public function __construct(
		public IncomingEvent $incoming,
		public array $candidates,
	) {
	}
}
