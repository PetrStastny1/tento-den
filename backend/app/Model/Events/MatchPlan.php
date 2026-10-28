<?php

declare(strict_types=1);

namespace App\Model\Events;


/**
 * What an import of one day changes: the result of EventMatcher, applied by the import service.
 */
final readonly class MatchPlan
{
	/**
	 * @param list<EventMatch> $matches incoming events that continue a stored event (same id)
	 * @param list<IncomingEvent> $inserts new events
	 * @param list<ReviewCase> $reviews undecided changes: stored and new events marked for review
	 * @param list<int> $missingIds stored active events no longer on the page
	 * @param list<int> $restoredIds originals of a review group whose exact text is back → active
	 * @param list<int> $rejectedIds members of such a group that are not on the page → missing
	 */
	public function __construct(
		public array $matches,
		public array $inserts,
		public array $reviews,
		public array $missingIds,
		public array $restoredIds = [],
		public array $rejectedIds = [],
	) {
	}
}
