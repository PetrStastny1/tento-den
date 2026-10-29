<?php

declare(strict_types=1);

namespace App\Console;

use App\Core\Console\Command;
use App\Core\Console\Output;
use App\Core\Console\UsageException;
use App\Model\Events\MonthDay;
use App\Model\Import\SourceException;
use App\Model\NameDays\NameDayParseException;
use App\Model\NameDays\SlovakNameDayImportService;


final class ImportSlovakNameDaysCommand implements Command
{
	public function __construct(
		private readonly SlovakNameDayImportService $import,
	) {
	}


	public function getName(): string
	{
		return 'name-days:import-sk';
	}


	public function getDescription(): string
	{
		return 'Importuje slovenské jmeniny ze stránek dnů sk.wikipedia (všechny dny, nebo --date=MM-DD)';
	}


	public function run(array $options, Output $output): int
	{
		$date = $options['date'] ?? null;
		if (array_diff(array_keys($options), ['date']) !== [] || ($date !== null && (!is_string($date) || !preg_match('~^\d{2}-\d{2}$~', $date)))) {
			throw new UsageException('Použití: name-days:import-sk [--date=MM-DD]');
		}
		try {
			$days = $date === null ? MonthDay::all() : [MonthDay::fromString($date)];
		} catch (\InvalidArgumentException) {
			throw new UsageException("Datum $date neexistuje.");
		}

		$failed = [];
		foreach ($days as $index => $day) {
			try {
				$result = $this->import->import($day);
				$output->writeln(sprintf('[%d/%d] %s ok (revize %d): %s', $index + 1, count($days), $day->format(), $result['revisionId'], implode(', ', $result['names']) ?: 'bez menin'));
			} catch (SourceException | NameDayParseException $e) {
				$failed[] = $day->format();
				$output->error(sprintf('[%d/%d] %s selhalo: %s', $index + 1, count($days), $day->format(), $e->getMessage()));
			}
		}
		if ($failed !== []) {
			$output->error(sprintf('Neúspěšné dny (%d): %s', count($failed), implode(', ', $failed)));
			return self::ExitFailure;
		}
		return self::ExitOk;
	}
}
