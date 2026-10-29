<?php

declare(strict_types=1);

namespace App\Model\Import;

use App\Model\Events\ArticleInfo;
use App\Model\Events\MonthDay;
use Nette\Database\Explorer;


/**
 * Read-only queries for the verification reports in bin/report/.
 */
final class ImportReportRepository
{
	public function __construct(
		private readonly Explorer $database,
	) {
	}


	/**
	 * @return array<string, int> status => days
	 */
	public function dayStatusCounts(): array
	{
		return array_map('intval', $this->database->fetchPairs('SELECT status, COUNT(*) FROM import_days GROUP BY status'));
	}


	/**
	 * @return list<array{status: string, section: string, events: int}>
	 */
	public function eventCounts(): array
	{
		return array_map(
			fn($row): array => ['status' => (string) $row->status, 'section' => (string) $row->section, 'events' => (int) $row->events],
			$this->database->fetchAll('SELECT status, section, COUNT(*) AS events FROM events GROUP BY status, section ORDER BY status, section'),
		);
	}


	/**
	 * Days imported successfully with the revision used.
	 * @return array<string, int> "MM-DD" => revision id
	 */
	public function okDays(): array
	{
		$days = [];
		foreach ($this->database->fetchAll("SELECT month, day, source_revision_id FROM import_days WHERE status = 'ok' ORDER BY month, day") as $row) {
			$days[sprintf('%02d-%02d', $row->month, $row->day)] = (int) $row->source_revision_id;
		}
		return $days;
	}


	/**
	 * @return array<string, array<string, int>> section => "MM-DD" => active events (days without events missing)
	 */
	public function activeEventsPerDay(): array
	{
		$counts = [];
		foreach ($this->database->fetchAll("SELECT month, day, section, COUNT(*) AS events FROM events WHERE status = 'active' GROUP BY month, day, section") as $row) {
			$counts[(string) $row->section][sprintf('%02d-%02d', $row->month, $row->day)] = (int) $row->events;
		}
		return $counts;
	}


	/**
	 * @return array{bySection: array<string, int>, withoutAnyArticle: int}
	 */
	public function eventsWithoutPrimary(): array
	{
		return [
			'bySection' => array_map('intval', $this->database->fetchPairs('SELECT section, COUNT(*) FROM events WHERE has_primary_article = 0 GROUP BY section')),
			'withoutAnyArticle' => (int) $this->database->fetchField('SELECT COUNT(*) FROM events e WHERE NOT EXISTS (SELECT 1 FROM event_articles ea WHERE ea.event_id = e.id)'),
		];
	}


	/**
	 * Every event with its linked articles in text order and the stored primary flag.
	 * @return array<int, array{hasPrimary: bool, articles: list<array{article: ArticleInfo, position: int, isPrimary: bool}>}>
	 */
	public function eventArticles(): array
	{
		$events = [];
		foreach ($this->database->fetchPairs('SELECT id, has_primary_article FROM events') as $id => $hasPrimary) {
			$events[(int) $id] = ['hasPrimary' => (bool) $hasPrimary, 'articles' => []];
		}
		$rows = $this->database->fetchAll('
			SELECT ea.event_id, ea.position, ea.is_primary, a.page_id, a.title, a.revision_id, a.wikibase_item
			FROM event_articles ea JOIN articles a ON a.page_id = ea.page_id
			ORDER BY ea.event_id, ea.position');
		foreach ($rows as $row) {
			$events[(int) $row->event_id] ??= ['hasPrimary' => false, 'articles' => []]; // the foreign key makes this unreachable
			$events[(int) $row->event_id]['articles'][] = [
				'article' => new ArticleInfo((int) $row->page_id, (string) $row->title, (int) $row->revision_id, $row->wikibase_item),
				'position' => (int) $row->position,
				'isPrimary' => (bool) $row->is_primary,
			];
		}
		return $events;
	}


	/**
	 * Page ids linked by the event with the given text, in text order; null when no such event
	 * is on the page (missing events are not considered).
	 * @return list<int>|null
	 */
	public function linkedPageIds(MonthDay $day, string $section, int $year, string $textHash): ?array
	{
		$id = $this->database->fetchField(
			"SELECT id FROM events WHERE month = ? AND day = ? AND section = ? AND year = ? AND text_hash = ? AND status <> 'missing'",
			$day->month,
			$day->day,
			$section,
			$year,
			$textHash,
		);
		if ($id === null) {
			return null;
		}
		return array_values(array_map('intval', $this->database->fetchPairs('SELECT position, page_id FROM event_articles WHERE event_id = ? ORDER BY position', $id)));
	}


	/**
	 * Linked articles per day over events still on the page (not missing).
	 * @return array<string, int> "MM-DD" => event_articles rows
	 */
	public function linksPerDay(): array
	{
		$counts = [];
		$rows = $this->database->fetchAll("
			SELECT e.month, e.day, COUNT(ea.page_id) AS links
			FROM events e LEFT JOIN event_articles ea ON ea.event_id = e.id
			WHERE e.status <> 'missing'
			GROUP BY e.month, e.day");
		foreach ($rows as $row) {
			$counts[sprintf('%02d-%02d', $row->month, $row->day)] = (int) $row->links;
		}
		return $counts;
	}
}
