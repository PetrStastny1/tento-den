<?php

declare(strict_types=1);

namespace App\Model\StateDays;

use Nette\Database\Explorer;


final class StateDayArticleRepository
{
	public function __construct(
		private readonly Explorer $database,
	) {
	}


	/**
	 * @param string $requestedTitle the article as configured
	 */
	public function save(string $requestedTitle, ArticleSummary $summary): void
	{
		$this->database->query('
			INSERT INTO state_day_articles (requested_title, title, extract, revision_id, fetched_at) VALUES (?, ?, ?, ?, ?)
			ON DUPLICATE KEY UPDATE title = VALUES(title), extract = VALUES(extract), revision_id = VALUES(revision_id), fetched_at = VALUES(fetched_at)',
			$requestedTitle, $summary->title, $summary->extract, $summary->revisionId, $summary->fetchedAt,
		);
	}


	/**
	 * @param list<string> $requestedTitles
	 * @return array<string, ArticleSummary> requested title => summary (missing ones are left out)
	 */
	public function findByTitles(array $requestedTitles): array
	{
		if ($requestedTitles === []) {
			return [];
		}
		$result = [];
		foreach ($this->database->fetchAll('SELECT * FROM state_day_articles WHERE requested_title IN (?)', $requestedTitles) as $row) {
			$result[(string) $row->requested_title] = new ArticleSummary(
				(string) $row->title,
				(string) $row->extract,
				(int) $row->revision_id,
				\DateTimeImmutable::createFromInterface($row->fetched_at),
			);
		}
		return $result;
	}
}
