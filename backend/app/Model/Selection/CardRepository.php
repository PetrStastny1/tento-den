<?php

declare(strict_types=1);

namespace App\Model\Selection;

use App\Model\Events\EventStatus;
use App\Model\Events\MonthDay;
use App\Model\Events\Section;
use Nette\Database\Explorer;
use Nette\Database\Row;


final class CardRepository
{
	private const Query = '
		SELECT e.id, e.month, e.day, e.year, e.section, e.text, e.status, e.source_title, e.source_revision_id, e.fetched_at,
			p.title AS primary_title, p.revision_id AS primary_revision_id, p.sitelinks AS primary_sitelinks
		FROM events e
		LEFT JOIN event_articles pe ON pe.event_id = e.id AND pe.is_primary = 1
		LEFT JOIN articles p ON p.page_id = pe.page_id';


	public function __construct(
		private readonly Explorer $database,
	) {
	}


	/**
	 * @return list<CardData>
	 */
	public function findActiveByDay(MonthDay $day): array
	{
		$rows = $this->database->fetchAll(
			self::Query . ' WHERE e.month = ? AND e.day = ? AND e.status = ? ORDER BY e.id',
			$day->month,
			$day->day,
			EventStatus::Active->value,
		);
		return $this->toCards($rows);
	}


	public function findById(int $id): ?CardData
	{
		return $this->toCards($this->database->fetchAll(self::Query . ' WHERE e.id = ?', $id))[0] ?? null;
	}


	/**
	 * @param list<Row> $rows
	 * @return list<CardData>
	 */
	private function toCards(array $rows): array
	{
		if ($rows === []) {
			return [];
		}
		$links = [];
		$linkRows = $this->database->fetchAll('
			SELECT ea.event_id, a.title, a.sitelinks
			FROM event_articles ea JOIN articles a ON a.page_id = ea.page_id
			WHERE ea.event_id IN (?)
			ORDER BY ea.event_id, ea.position', array_map(fn(Row $row): int => $row->id, $rows));
		foreach ($linkRows as $link) {
			$links[$link->event_id][] = ['title' => (string) $link->title, 'sitelinks' => $link->sitelinks === null ? null : (int) $link->sitelinks];
		}

		return array_map(fn(Row $row): CardData => new CardData(
			id: $row->id,
			day: new MonthDay($row->month, $row->day),
			year: $row->year,
			section: Section::from($row->section),
			text: $row->text,
			status: EventStatus::from($row->status),
			sourceTitle: $row->source_title,
			sourceRevisionId: $row->source_revision_id,
			fetchedAt: \DateTimeImmutable::createFromInterface($row->fetched_at),
			primaryTitle: $row->primary_title,
			primaryRevisionId: $row->primary_revision_id,
			primarySitelinks: $row->primary_sitelinks,
			links: $links[$row->id] ?? [],
		), $rows);
	}
}
