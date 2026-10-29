<?php

declare(strict_types=1);

namespace App\Model\Anniversaries;

use Nette\Database\Explorer;
use Nette\Utils\Json;


final class ArticleEventRepository
{
	public function __construct(
		private readonly Explorer $database,
	) {
	}


	/**
	 * Wikidata items of the primary articles of active events (only unchecked ones unless $all).
	 * @return list<string>
	 */
	public function primaryItems(bool $all): array
	{
		return array_map(fn($row): string => (string) $row->wikibase_item, $this->database->fetchAll('
			SELECT DISTINCT a.wikibase_item
			FROM events e
			JOIN event_articles ea ON ea.event_id = e.id AND ea.is_primary = 1
			JOIN articles a ON a.page_id = ea.page_id
			WHERE e.status = ? AND a.wikibase_item IS NOT NULL' . ($all ? '' : ' AND a.event_checked_at IS NULL') . '
			ORDER BY a.wikibase_item', 'active'));
	}


	/**
	 * @param list<int> $years
	 */
	public function save(string $item, ?string $class, array $years, \DateTimeImmutable $now): void
	{
		$this->database->query(
			'UPDATE articles SET event_class = ?, event_years = ?, event_checked_at = ? WHERE wikibase_item = ?',
			$class,
			$years === [] ? null : Json::encode($years),
			$now,
			$item,
		);
	}
}
