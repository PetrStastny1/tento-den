<?php

declare(strict_types=1);

namespace App\Model\Import;

use App\Model\Events\MonthDay;
use Nette\Database\Explorer;


/**
 * State of imports: one row per day (ok/error) and year runs that can be resumed.
 */
final class ImportStatusRepository
{
	public function __construct(
		private readonly Explorer $database,
	) {
	}


	public function markOk(MonthDay $day, int $revisionId, int $eventsCount, ?int $runId, \DateTimeImmutable $now): void
	{
		$this->upsertDay($day, [
			'status' => 'ok',
			'last_attempt_at' => $now,
			'last_success_at' => $now,
			'source_revision_id' => $revisionId,
			'events_count' => $eventsCount,
			'error_message' => null,
			'last_run_id' => $runId,
		]);
	}


	/**
	 * Keeps the data of the last success (revision, count, time) untouched.
	 */
	public function markError(MonthDay $day, string $message, ?int $runId, \DateTimeImmutable $now): void
	{
		$this->upsertDay($day, [
			'status' => 'error',
			'last_attempt_at' => $now,
			'error_message' => mb_substr($message, 0, 2000),
			'last_run_id' => $runId,
		]);
	}


	public function startRun(\DateTimeImmutable $now): int
	{
		$this->database->query('INSERT INTO import_runs', ['started_at' => $now]);
		return (int) $this->database->getInsertId();
	}


	public function finishRun(int $runId, \DateTimeImmutable $now): void
	{
		$this->database->query('UPDATE import_runs SET finished_at = ? WHERE id = ?', $now, $runId);
	}


	public function latestRunId(): ?int
	{
		$id = $this->database->fetchField('SELECT MAX(id) FROM import_runs');
		return $id === null ? null : (int) $id;
	}


	/**
	 * Days that ended "ok" within the given run ("MM-DD").
	 * @return list<string>
	 */
	public function succeededDays(int $runId): array
	{
		return array_map(
			fn($row): string => sprintf('%02d-%02d', $row->month, $row->day),
			$this->database->fetchAll('SELECT month, day FROM import_days WHERE last_run_id = ? AND status = ?', $runId, 'ok'),
		);
	}


	/**
	 * @param array<string, mixed> $values
	 */
	private function upsertDay(MonthDay $day, array $values): void
	{
		$this->database->query(
			'INSERT INTO import_days ? ON DUPLICATE KEY UPDATE ?',
			['month' => $day->month, 'day' => $day->day] + $values,
			$values,
		);
	}
}
