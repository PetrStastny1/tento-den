<?php

declare(strict_types=1);

namespace App\Model\Events;


/**
 * What an import of one day changes: the result of EventMatcher, applied by the repository.
 */
final readonly class MatchPlan
{
	/**
	 * @param list<EventMatch> $matches incoming events that continue a stored event (same id)
	 * @param list<IncomingEvent> $inserts new events
	 * @param list<ReviewCase> $reviews undecidable edits: stored as new events marked for review
	 * @param list<int> $missingIds stored events no longer on the page
	 */
	public function __construct(
		public array $matches,
		public array $inserts,
		public array $reviews,
		public array $missingIds,
	) {
	}
}
