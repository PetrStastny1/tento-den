<?php

declare(strict_types=1);

namespace App\Model\Events;


/**
 * Pairs freshly parsed events of one day with stored ones, so an edited sentence keeps its id.
 * Similarity only helps to keep identity – it says nothing about whether the new text is true.
 *
 * Within the same section and year:
 * 1. Exact: same normalized text → same event (missing comes back to active).
 * 2. Restore: if a review group has an original back on the page with exactly its text and
 *    none of its replacements, the originals are active again and the rest of the group missing
 *    (typically vandalism reverted).
 * 3. Similar: a 1:1 pair that is the mutual best match, clearly ahead of the runner-up, and
 *    similar enough – or a bit less similar but with the same primary article.
 * 4. Whatever is left is not guessed: if new events appear while stored events of the same
 *    section and year disappear (or are still in review), all of them go to one review case –
 *    however dissimilar they are. New events alone are inserts, stored events alone go missing.
 *
 * Events in review are only ever matched exactly and never continued by a similar text,
 * which makes repeated imports of the same revision idempotent.
 */
final class EventMatcher
{
	public const AutoMatch = 0.70;
	public const AutoMatchSameArticle = 0.60;
	/** an already missing event joins a review only when it resembles a new event this much */
	public const MissingCandidate = 0.30;
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

		$parts = [];
		foreach (array_keys($storedByGroup + $incomingByGroup) as $key) {
			$parts[] = $this->matchGroup($storedByGroup[$key] ?? [], $incomingByGroup[$key] ?? []);
		}
		return new MatchPlan(
			matches: array_merge(...array_map(fn(MatchPlan $p): array => $p->matches, $parts)),
			inserts: array_merge(...array_map(fn(MatchPlan $p): array => $p->inserts, $parts)),
			reviews: array_merge(...array_map(fn(MatchPlan $p): array => $p->reviews, $parts)),
			missingIds: array_merge(...array_map(fn(MatchPlan $p): array => $p->missingIds, $parts)),
			restoredIds: array_merge(...array_map(fn(MatchPlan $p): array => $p->restoredIds, $parts)),
			rejectedIds: array_merge(...array_map(fn(MatchPlan $p): array => $p->rejectedIds, $parts)),
		);
	}


	/**
	 * @param list<StoredEvent> $stored
	 * @param list<IncomingEvent> $incoming
	 */
	private function matchGroup(array $stored, array $incoming): MatchPlan
	{
		$matches = [];
		[$unmatchedStored, $incoming] = $this->matchExactly($stored, $incoming, $matches);
		[$restored, $rejected] = $this->restoreReviewGroups($stored, $matches);
		$settled = array_fill_keys([...$restored, ...$rejected], true);
		$unmatchedStored = array_values(array_filter($unmatchedStored, fn(StoredEvent $e): bool => !isset($settled[$e->id])));

		// only active and missing events may be continued by an edited text
		$candidates = array_values(array_filter($unmatchedStored, fn(StoredEvent $e): bool => $e->status !== EventStatus::NeedsReview));
		$similarity = $this->similarityMatrix($incoming, $candidates);
		[$fuzzyMatches, $matchedIncoming, $matchedCandidates] = $this->matchSimilar($incoming, $candidates, $similarity);
		array_push($matches, ...$fuzzyMatches);

		$leftIncoming = array_values(array_filter($incoming, fn($e, int $i): bool => !isset($matchedIncoming[$i]), ARRAY_FILTER_USE_BOTH));
		$leftStored = [];
		foreach ($unmatchedStored as $event) {
			$c = array_search($event, $candidates, true);
			if ($c === false || !isset($matchedCandidates[$c])) {
				$leftStored[] = $event;
			}
		}

		if ($leftIncoming === []) {
			// nothing new: active events that vanished are missing, events in review wait for a decision
			$missing = array_map(fn(StoredEvent $e): int => $e->id, array_filter($leftStored, fn(StoredEvent $e): bool => $e->status === EventStatus::Active));
			return new MatchPlan($matches, [], [], array_values($missing), $restored, $rejected);
		}

		$reviewCandidates = [];
		foreach ($leftStored as $event) {
			$best = max(array_map(fn(IncomingEvent $new): float => EventText::similarity($event->text, $new->parsed->text), $leftIncoming));
			if ($event->status !== EventStatus::Missing || $best >= self::MissingCandidate) {
				$reviewCandidates[$event->id] = round($best, 2);
			}
		}

		if ($reviewCandidates === []) {
			return new MatchPlan($matches, $leftIncoming, [], [], $restored, $rejected);
		}
		return new MatchPlan($matches, [], [new ReviewCase($leftIncoming, $reviewCandidates)], [], $restored, $rejected);
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
	 * A review group is settled when at least one original is back with exactly its text
	 * and no replacement is on the page.
	 * @param list<StoredEvent> $stored
	 * @param list<EventMatch> $exactMatches
	 * @return array{list<int>, list<int>} [restored originals, rejected rest of the group]
	 */
	private function restoreReviewGroups(array $stored, array $exactMatches): array
	{
		$present = [];
		foreach ($exactMatches as $match) {
			$present[$match->stored->id] = true;
		}
		$groups = [];
		foreach ($stored as $event) {
			if ($event->status === EventStatus::NeedsReview && $event->reviewGroup !== null) {
				$groups[$event->reviewGroup][] = $event;
			}
		}

		$restored = $rejected = [];
		foreach ($groups as $members) {
			$presentOriginals = array_filter($members, fn(StoredEvent $e): bool => $e->reviewRole === ReviewRole::Original && isset($present[$e->id]));
			$presentReplacements = array_filter($members, fn(StoredEvent $e): bool => $e->reviewRole === ReviewRole::Replacement && isset($present[$e->id]));
			if ($presentOriginals === [] || $presentReplacements !== []) {
				continue;
			}
			foreach ($members as $event) {
				if (isset($present[$event->id])) {
					$restored[] = $event->id;
				} else {
					$rejected[] = $event->id;
				}
			}
		}
		return [$restored, $rejected];
	}


	/**
	 * @param list<IncomingEvent> $incoming
	 * @param list<StoredEvent> $candidates
	 * @return array<int, array<int, float>> [incoming index][candidate index]
	 */
	private function similarityMatrix(array $incoming, array $candidates): array
	{
		$similarity = [];
		foreach ($incoming as $i => $new) {
			foreach ($candidates as $c => $old) {
				$similarity[$i][$c] = EventText::similarity($old->text, $new->parsed->text);
			}
		}
		return $similarity;
	}


	/**
	 * @param list<IncomingEvent> $incoming
	 * @param list<StoredEvent> $candidates
	 * @param array<int, array<int, float>> $similarity
	 * @return array{list<EventMatch>, array<int, true>, array<int, true>}
	 */
	private function matchSimilar(array $incoming, array $candidates, array $similarity): array
	{
		$matches = $matchedIncoming = $matchedCandidates = [];
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
		return [$matches, $matchedIncoming, $matchedCandidates];
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
