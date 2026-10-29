<?php

declare(strict_types=1);

namespace App\Model\NameDays;

use App\Model\Import\ArticleSummary;
use Nette\Database\Explorer;


final class NameInfoRepository
{
	public function __construct(
		private readonly Explorer $database,
	) {
	}


	public function save(NameInfo $info): void
	{
		$values = [
			'wikidata_item' => $info->wikidataItem,
			'article_title' => $info->meaning?->title,
			'extract' => $info->meaning?->extract,
			'article_revision_id' => $info->meaning?->revisionId,
			'bearer_item' => $info->bearer?->wikidataItem,
			'bearer_name' => $info->bearer?->name,
			'bearer_article' => $info->bearer?->article,
			'bearer_sitelinks' => $info->bearer?->sitelinks,
			'bearer_occupations' => $info->bearer?->occupations,
			'checked_at' => $info->checkedAt,
		];
		$this->database->query(
			'INSERT INTO name_infos ? ON DUPLICATE KEY UPDATE ?',
			['language' => $info->country->language(), 'name' => $info->name] + $values,
			$values,
		);
	}


	/**
	 * @param list<string> $names
	 * @return array<string, NameInfo>
	 */
	public function findByNames(Country $country, array $names): array
	{
		if ($names === []) {
			return [];
		}
		$result = [];
		foreach ($this->database->fetchAll('SELECT * FROM name_infos WHERE language = ? AND name IN (?)', $country->language(), $names) as $row) {
			$checkedAt = \DateTimeImmutable::createFromInterface($row->checked_at);
			$result[(string) $row->name] = new NameInfo(
				(string) $row->name,
				$country,
				$row->wikidata_item === null ? null : (string) $row->wikidata_item,
				$row->article_title === null ? null : new ArticleSummary((string) $row->article_title, (string) $row->extract, (int) $row->article_revision_id, $checkedAt),
				$row->bearer_item === null ? null : new Bearer((string) $row->bearer_item, (string) $row->bearer_name, (string) $row->bearer_article, (int) $row->bearer_sitelinks, $row->bearer_occupations === null ? null : (string) $row->bearer_occupations),
				$checkedAt,
			);
		}
		return $result;
	}


	/**
	 * @return array{names: int, meaning: int, bearer: int}
	 */
	public function stats(Country $country): array
	{
		$row = $this->database->fetch(
			'SELECT COUNT(*) AS names, COUNT(article_title) AS meaning, COUNT(bearer_item) AS bearer FROM name_infos WHERE language = ?',
			$country->language(),
		);
		return ['names' => (int) $row?->names, 'meaning' => (int) $row?->meaning, 'bearer' => (int) $row?->bearer];
	}
}
