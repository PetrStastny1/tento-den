<?php

declare(strict_types=1);

namespace App\Model\Events;

use Nette\Database\Explorer;
use Nette\Database\Row;


final class EventRepository
{
	private const SummaryQuery = '
		SELECT e.*, a.title AS primary_title, a.revision_id AS primary_revision_id
		FROM events e
		LEFT JOIN event_articles ea ON ea.event_id = e.id AND ea.is_primary = 1
		LEFT JOIN articles a ON a.page_id = ea.page_id';


	public function __construct(
		private readonly Explorer $database,
	) {
	}


	/**
	 * All stored events of the day regardless of status, as matching needs them.
	 * @return list<StoredEvent>
	 */
	public function findForMatching(MonthDay $day): array
	{
		$rows = $this->database->fetchAll('
			SELECT e.id, e.section, e.year, e.text, e.text_hash, e.status, e.review_group, e.review_role, ea.page_id AS primary_page_id
			FROM events e
			LEFT JOIN event_articles ea ON ea.event_id = e.id AND ea.is_primary = 1
			WHERE e.month = ? AND e.day = ?
			ORDER BY e.id', $day->month, $day->day);

		return array_map(fn(Row $row): StoredEvent => new StoredEvent(
			id: $row->id,
			section: Section::from($row->section),
			year: $row->year,
			text: $row->text,
			textHash: $row->text_hash,
			status: EventStatus::from($row->status),
			primaryPageId: $row->primary_page_id,
			reviewGroup: $row->review_group,
			reviewRole: $row->review_role === null ? null : ReviewRole::from($row->review_role),
		), $rows);
	}


	public function countActive(MonthDay $day): int
	{
		return (int) $this->database->fetchField(
			'SELECT COUNT(*) FROM events WHERE month = ? AND day = ? AND status = ?',
			$day->month,
			$day->day,
			EventStatus::Active->value,
		);
	}


	public function insert(
		MonthDay $day,
		IncomingEvent $event,
		string $sourceTitle,
		int $sourceRevisionId,
		EventStatus $status,
		?string $reviewNote,
		\DateTimeImmutable $now,
	): int {
		$this->database->query('INSERT INTO events', [
			'month' => $day->month,
			'day' => $day->day,
			'year' => $event->parsed->year,
			'section' => $event->parsed->section->value,
			'status' => $status->value,
			'review_note' => $reviewNote,
		] + $this->importedColumns($event, $sourceTitle, $sourceRevisionId, $now));
		$id = (int) $this->database->getInsertId();
		$this->replaceArticles($id, $event);
		return $id;
	}


	/**
	 * Continues a stored event with the freshly imported text; a missing event becomes active again,
	 * an event in review stays in review.
	 */
	public function updateFromImport(StoredEvent $stored, IncomingEvent $event, string $sourceTitle, int $sourceRevisionId, \DateTimeImmutable $now): void
	{
		$status = $stored->status === EventStatus::Missing ? EventStatus::Active : $stored->status;
		$this->database->query('UPDATE events SET ? WHERE id = ?', [
			'status' => $status->value,
			'missing_since' => null,
		] + $this->importedColumns($event, $sourceTitle, $sourceRevisionId, $now), $stored->id);
		$this->replaceArticles($stored->id, $event);
	}


	/**
	 * @param list<int> $ids
	 */
	public function markMissing(array $ids, \DateTimeImmutable $now): void
	{
		if ($ids === []) {
			return;
		}
		$this->database->query(
			'UPDATE events SET status = ?, missing_since = ? WHERE id IN (?) AND status = ?',
			EventStatus::Missing->value,
			$now,
			$ids,
			EventStatus::Active->value,
		);
	}


	/**
	 * Puts the event into a review group; an event keeps the role it already had in an earlier group.
	 */
	public function markForReview(int $id, string $note, int $group, ReviewRole $role): void
	{
		$this->database->query(
			'UPDATE events SET status = ?, review_note = ?, review_group = ?, review_role = COALESCE(review_role, ?) WHERE id = ?',
			EventStatus::NeedsReview->value,
			mb_substr($note, 0, 500),
			$group,
			$role->value,
			$id,
		);
	}


	/**
	 * @param list<int> $ids originals whose exact text is back on the page
	 */
	public function restoreFromReview(array $ids): void
	{
		if ($ids !== []) {
			$this->database->query(
				'UPDATE events SET status = ?, review_note = NULL, review_group = NULL, review_role = NULL, missing_since = NULL WHERE id IN (?)',
				EventStatus::Active->value,
				$ids,
			);
		}
	}


	/**
	 * @param list<int> $ids members of a settled review group that are not on the page
	 */
	public function rejectFromReview(array $ids, string $note, \DateTimeImmutable $now): void
	{
		if ($ids !== []) {
			$this->database->query(
				'UPDATE events SET status = ?, missing_since = ?, review_note = ?, review_group = NULL, review_role = NULL WHERE id IN (?)',
				EventStatus::Missing->value,
				$now,
				mb_substr($note, 0, 500),
				$ids,
			);
		}
	}


	/**
	 * @return list<EventSummary>
	 */
	public function findByDay(MonthDay $day): array
	{
		$rows = $this->database->fetchAll(
			self::SummaryQuery . ' WHERE e.month = ? AND e.day = ? ORDER BY e.section, e.position, e.id',
			$day->month,
			$day->day,
		);
		return array_map($this->toSummary(...), $rows);
	}


	/**
	 * @return list<EventSummary>
	 */
	public function findForReview(): array
	{
		$rows = $this->database->fetchAll(
			self::SummaryQuery . ' WHERE e.status = ? ORDER BY e.month, e.day, e.section, e.year, e.id',
			EventStatus::NeedsReview->value,
		);
		return array_map($this->toSummary(...), $rows);
	}


	/**
	 * @return array<string, mixed>
	 */
	private function importedColumns(IncomingEvent $event, string $sourceTitle, int $sourceRevisionId, \DateTimeImmutable $now): array
	{
		return [
			'position' => $event->parsed->position,
			'text' => $event->parsed->text,
			'text_hash' => $event->textHash,
			'source_title' => $sourceTitle,
			'source_revision_id' => $sourceRevisionId,
			'has_primary_article' => (int) ($event->primaryArticle !== null),
			'fetched_at' => $now,
		];
	}


	private function replaceArticles(int $eventId, IncomingEvent $event): void
	{
		$this->database->query('DELETE FROM event_articles WHERE event_id = ?', $eventId);
		$rows = [];
		foreach ($event->articles as $position => $article) {
			$rows[] = [
				'event_id' => $eventId,
				'page_id' => $article->pageId,
				'position' => $position + 1,
				'is_primary' => (int) ($article->pageId === $event->primaryArticle?->pageId),
			];
		}
		if ($rows !== []) {
			$this->database->query('INSERT INTO event_articles', $rows);
		}
	}


	private function toSummary(Row $row): EventSummary
	{
		return new EventSummary(
			id: $row->id,
			month: $row->month,
			day: $row->day,
			section: Section::from($row->section),
			year: $row->year,
			text: $row->text,
			status: EventStatus::from($row->status),
			sourceTitle: $row->source_title,
			sourceRevisionId: $row->source_revision_id,
			primaryTitle: $row->primary_title,
			primaryRevisionId: $row->primary_revision_id,
			reviewNote: $row->review_note,
		);
	}
}
