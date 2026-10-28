<?php

declare(strict_types=1);

namespace App\Console;

use App\Core\Console\Command;
use App\Core\Console\Output;
use App\Core\Console\UsageException;
use App\Model\Events\MonthDay;
use App\Model\Import\DayImportResult;
use App\Model\Import\ImportFailedException;
use App\Model\Import\YearImportService;


final class ImportYearCommand implements Command
{
	public function __construct(
		private readonly YearImportService $yearImport,
	) {
	}


	public function getName(): string
	{
		return 'import:year';
	}


	public function getDescription(): string
	{
		return 'Importuje všech 366 dní (--resume naváže na poslední běh)';
	}


	public function run(array $options, Output $output): int
	{
		if (array_diff(array_keys($options), ['resume']) !== [] || !in_array($options['resume'] ?? true, [true], true)) {
			throw new UsageException('Použití: import:year [--resume]');
		}

		$failed = $this->yearImport->import(
			isset($options['resume']),
			function (int $done, int $total, MonthDay $day, DayImportResult|ImportFailedException|null $result) use ($output): void {
				$prefix = sprintf('[%3d/%d] ', $done, $total);
				match (true) {
					$result === null => $output->writeln($prefix . $day->format() . ' přeskočeno (už hotovo v tomto běhu)'),
					$result instanceof DayImportResult => $output->writeln($prefix . ImportDayCommand::describe($result)),
					default => $output->error($prefix . $result->getMessage()),
				};
			},
		);

		if ($failed === []) {
			$output->writeln('Hotovo, všechny dny v pořádku.');
			return self::ExitOk;
		}
		$output->error(sprintf('Neúspěšné dny (%d): %s', count($failed), implode(', ', array_keys($failed))));
		$output->error('Znovu jen je: import:year --resume');
		return self::ExitFailure;
	}
}
