<?php

declare(strict_types=1);

namespace App\Model\Events;

use Nette\Database\Explorer;


final class ArticleRepository
{
	public function __construct(
		private readonly Explorer $database,
	) {
	}


	/**
	 * @param list<ArticleInfo> $articles
	 */
	public function upsert(array $articles, \DateTimeImmutable $now): void
	{
		foreach ($articles as $article) {
			$this->database->query(
				'INSERT INTO articles ? ON DUPLICATE KEY UPDATE title = VALUES(title), revision_id = VALUES(revision_id),
					wikibase_item = VALUES(wikibase_item), fetched_at = VALUES(fetched_at)',
				[
					'page_id' => $article->pageId,
					'title' => $article->title,
					'revision_id' => $article->revisionId,
					'wikibase_item' => $article->wikibaseItem,
					'fetched_at' => $now,
				],
			);
		}
	}


	/**
	 * How many events link each article – input for curating the list of generic articles.
	 * @return list<array{title: string, events: int}>
	 */
	public function linkFrequencies(int $minEvents): array
	{
		$rows = $this->database->fetchAll('
			SELECT a.title, COUNT(*) AS events
			FROM event_articles ea
			JOIN articles a ON a.page_id = ea.page_id
			GROUP BY a.page_id, a.title
			HAVING events >= ?
			ORDER BY events DESC, a.title', $minEvents);

		return array_map(fn($row): array => ['title' => (string) $row->title, 'events' => (int) $row->events], $rows);
	}
}
