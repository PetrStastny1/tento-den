<?php

declare(strict_types=1);

namespace App\Model\Events;


/**
 * Pairs freshly parsed events of one day with stored ones, so an edited sentence keeps its id.
 *
 * Within the same section and year:
 * 1. Exact: same normalized text → same event (missing comes back to active, review stays review).
 * 2. Similar: a 1:1 pair that is the mutual best match, clearly ahead of the runner-up, and
 *    similar enough – or a bit less similar but with the same primary article.
 * 3. Anything else with a plausible candidate is not guessed: it goes to review together
 *    with its candidates. Stored active events without any counterpart become missing.
 *
 * Events in review are only ever matched exactly, which makes repeated imports of the same
 * revision idempotent: the event created for review is found again by its text.
 */
final class EventMatcher
{
	public const AutoMatch = 0.70;
	public const AutoMatchSameArticle = 0.60;
	public const Candidate = 0.30;
	public const Margin = 0.15;


	/**
	 * @param list<StoredEvent> $stored all events of the day
	 * @param list<IncomingEvent> $incoming all parsed events of the day
	 */
	public function match(array $stored, array $incoming): MatchPlan
	{
		$storedByGroup = [];
		foreach ($stored as $event) {
			$storedByGroup[$event->section->value . '|' . $event->year][] = $event;
		}
		$incomingByGroup = [];
		foreach ($incoming as $event) {
			$incomingByGroup[$event->groupKey()][] = $event;
		}

		$matches = $inserts = $reviews = $missing = [];
		foreach (array_keys($storedByGroup + $incomingByGroup) as $key) {
			$plan = $this->matchGroup($storedByGroup[$key] ?? [], $incomingByGroup[$key] ?? []);
			array_push($matches, ...$plan->matches);
			array_push($inserts, ...$plan->inserts);
			array_push($reviews, ...$plan->reviews);
			array_push($missing, ...$plan->missingIds);
		}
		return new MatchPlan($matches, $inserts, $reviews, $missing);
	}


	/**
	 * @param list<StoredEvent> $stored
	 * @param list<IncomingEvent> $incoming
	 */
	private function matchGroup(array $stored, array $incoming): MatchPlan
	{
		$matches = [];
		[$stored, $incoming] = $this->matchExactly($stored, $incoming, $matches);

		// only active and missing events may be continued by an edited text
		$candidates = array_values(array_filter($stored, fn(StoredEvent $e): bool => $e->status !== EventStatus::NeedsReview));
		$similarity = [];
		foreach ($incoming as $i => $new) {
			foreach ($candidates as $c => $old) {
				$similarity[$i][$c] = EventText::similarity($old->text, $new->parsed->text);
			}
		}

		$matchedIncoming = $matchedCandidates = [];
		foreach ($incoming as $i => $new) {
			$c = $this->bestIndex($similarity[$i] ?? []);
			if ($c === null || $this->bestIndex(array_column($similarity, $c)) !== $i) {
				continue; // not mutual
			}
			$score = $similarity[$i][$c];
			$sameArticle = $new->primaryArticle !== null && $new->primaryArticle->pageId === $candidates[$c]->primaryPageId;
			if (
				($score >= self::AutoMatch || ($sameArticle && $score >= self::AutoMatchSameArticle))
				&& $this->runnerUp($similarity[$i], $c) <= $score - self::Margin
				&& $this->runnerUp(array_column($similarity, $c), $i) <= $score - self::Margin
			) {
				$matches[] = new EventMatch($candidates[$c], $new, false, $score);
				$matchedIncoming[$i] = $matchedCandidates[$c] = true;
			}
		}

		$inserts = $reviews = [];
		$inReview = [];
		foreach ($incoming as $i => $new) {
			if (isset($matchedIncoming[$i])) {
				continue;
			}
			$plausible = [];
			foreach ($candidates as $c => $old) {
				if (!isset($matchedCandidates[$c]) && $similarity[$i][$c] >= self::Candidate) {
					$plausible[$old->id] = round($similarity[$i][$c], 2);
					$inReview[$old->id] = true;
				}
			}
			if ($plausible === []) {
				$inserts[] = $new;
			} else {
				$reviews[] = new ReviewCase($new, $plausible);
			}
		}

		$missing = [];
		foreach ($candidates as $c => $old) {
			if (!isset($matchedCandidates[$c]) && !isset($inReview[$old->id]) && $old->status === EventStatus::Active) {
				$missing[] = $old->id;
			}
		}

		return new MatchPlan($matches, $inserts, $reviews, $missing);
	}


	/**
	 * @param list<StoredEvent> $stored
	 * @param list<IncomingEvent> $incoming
	 * @param list<EventMatch> $matches
	 * @return array{list<StoredEvent>, list<IncomingEvent>} what is left unmatched
	 */
	private function matchExactly(array $stored, array $incoming, array &$matches): array
	{
		// prefer continuing an active event over reviving a missing one or one in review
		$order = [EventStatus::Active->value => 0, EventStatus::Missing->value => 1, EventStatus::NeedsReview->value => 2];
		usort($stored, fn(StoredEvent $a, StoredEvent $b): int => [$order[$a->status->value], $a->id] <=> [$order[$b->status->value], $b->id]);

		$leftIncoming = [];
		foreach ($incoming as $new) {
			foreach ($stored as $s => $old) {
				if ($old->textHash === $new->textHash) {
					$matches[] = new EventMatch($old, $new, true, 1.0);
					unset($stored[$s]);
					continue 2;
				}
			}
			$leftIncoming[] = $new;
		}
		return [array_values($stored), $leftIncoming];
	}


	/**
	 * @param array<int, float> $scores
	 */
	private function bestIndex(array $scores): ?int
	{
		if ($scores === []) {
			return null;
		}
		arsort($scores);
		return array_key_first($scores);
	}


	/**
	 * @param array<int, float> $scores
	 */
	private function runnerUp(array $scores, int $best): float
	{
		unset($scores[$best]);
		return $scores === [] ? 0.0 : max($scores);
	}
}
