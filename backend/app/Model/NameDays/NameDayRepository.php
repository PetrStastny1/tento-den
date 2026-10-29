<?php

declare(strict_types=1);

namespace App\Model\NameDays;

use App\Model\Events\MonthDay;
use Nette\Database\Explorer;


final class NameDayRepository
{
	public function __construct(
		private readonly Explorer $database,
	) {
	}


	/**
	 * The page is the source of truth: the name days it gave for the day are replaced as a whole.
	 * @param list<ParsedNameDay> $nameDays
	 */
	public function replaceForDay(MonthDay $day, string $sourceWiki, array $nameDays, string $sourceTitle, int $revisionId, \DateTimeImmutable $now): void
	{
		$this->database->query('DELETE FROM name_days WHERE month = ? AND day = ? AND source_wiki = ?', $day->month, $day->day, $sourceWiki);
		if ($nameDays === []) {
			return;
		}
		$this->database->query('INSERT INTO name_days', array_map(fn(ParsedNameDay $n): array => [
			'month' => $day->month,
			'day' => $day->day,
			'source_wiki' => $sourceWiki,
			'country' => $n->country->value,
			'name' => $n->name,
			'line' => $n->line,
			'position' => $n->position,
			'note' => $n->note,
			'source_title' => $sourceTitle,
			'source_revision_id' => $revisionId,
			'fetched_at' => $now,
		], $nameDays));
	}


	/**
	 * Name days of a country from its own Wikipedia (cz from cs, sk from sk), in page order.
	 * @return list<NameDay>
	 */
	public function findForDay(MonthDay $day, Country $country): array
	{
		$rows = $this->database->fetchAll('
			SELECT * FROM name_days WHERE month = ? AND day = ? AND country = ? AND source_wiki = ? ORDER BY line, position',
			$day->month, $day->day, $country->value, $country->language());
		return array_map(fn($row): NameDay => new NameDay(
			Country::from((string) $row->country),
			(string) $row->name,
			(int) $row->line,
			$row->note === null ? null : (string) $row->note,
			(string) $row->source_title,
			(int) $row->source_revision_id,
			\DateTimeImmutable::createFromInterface($row->fetched_at),
		), $rows);
	}


	/**
	 * Names shown in a mode (line 1 of the country from its own Wikipedia) – whose meaning and bearer are looked up.
	 * @return list<string>
	 */
	public function mainNames(Country $country): array
	{
		return array_map(fn($row): string => (string) $row->name, $this->database->fetchAll(
			'SELECT DISTINCT name FROM name_days WHERE line = 1 AND country = ? AND source_wiki = ? ORDER BY name',
			$country->value,
			$country->language(),
		));
	}


	/**
	 * Slovak names by day from both sources, for comparing the calendars.
	 * @return array<string, array{cs: list<string>, sk: list<string>}> MM-DD => names
	 */
	public function slovakBySource(): array
	{
		$result = [];
		foreach ($this->database->fetchAll("SELECT month, day, source_wiki, name FROM name_days WHERE country = 'sk' ORDER BY month, day, line, position") as $row) {
			$key = sprintf('%02d-%02d', $row->month, $row->day);
			$result[$key] ??= ['cs' => [], 'sk' => []];
			$result[$key][(string) $row->source_wiki === 'cs' ? 'cs' : 'sk'][] = (string) $row->name;
		}
		return $result;
	}


	/**
	 * @return array{days: int, names: int}
	 */
	public function stats(Country $country): array
	{
		$row = $this->database->fetch(
			'SELECT COUNT(DISTINCT month, day) AS days, COUNT(DISTINCT name) AS names FROM name_days WHERE country = ? AND source_wiki = ?',
			$country->value,
			$country->language(),
		);
		return ['days' => (int) $row?->days, 'names' => (int) $row?->names];
	}
}
