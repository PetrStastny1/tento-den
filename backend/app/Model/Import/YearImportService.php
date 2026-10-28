<?php

declare(strict_types=1);

namespace App\Model\Import;

use App\Core\Clock\Clock;
use App\Model\Events\MonthDay;


/**
 * Imports all 366 days within one run. A failed day does not stop the run; resuming
 * continues the latest run and skips days that already succeeded in it.
 */
final class YearImportService
{
	public function __construct(
		private readonly DayImportService $dayImport,
		private readonly ImportStatusRepository $statusRepository,
		private readonly Clock $clock,
	) {
	}


	/**
	 * @param \Closure(int $done, int $total, MonthDay $day, DayImportResult|ImportFailedException|null $result): void $progress
	 *        $result is null for a day skipped when resuming
	 * @return array<string, string> failed days "MM-DD" => reason
	 */
	public function import(bool $resume, \Closure $progress): array
	{
		$runId = $resume ? $this->statusRepository->latestRunId() : null;
		$runId ??= $this->statusRepository->startRun($this->clock->now());
		$succeeded = array_fill_keys($this->statusRepository->succeededDays($runId), true);

		$days = MonthDay::all();
		$failed = [];
		foreach ($days as $index => $day) {
			if (isset($succeeded[$day->format()])) {
				$progress($index + 1, count($days), $day, null);
				continue;
			}
			try {
				$progress($index + 1, count($days), $day, $this->dayImport->import($day, $runId));
			} catch (ImportFailedException $e) {
				$failed[$day->format()] = $e->getMessage();
				$progress($index + 1, count($days), $day, $e);
			}
		}

		$this->statusRepository->finishRun($runId, $this->clock->now());
		return $failed;
	}
}
