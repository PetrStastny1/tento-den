<?php

declare(strict_types=1);

namespace App\Console;

use App\Core\Console\Command;
use App\Core\Console\Output;
use App\Core\Console\UsageException;
use App\Model\Events\MonthDay;
use App\Model\Import\DayImportResult;
use App\Model\Import\DayImportService;
use App\Model\Import\ImportFailedException;


final class ImportDayCommand implements Command
{
	public function __construct(
		private readonly DayImportService $dayImport,
	) {
	}


	public function getName(): string
	{
		return 'import:day';
	}


	public function getDescription(): string
	{
		return 'Importuje události jednoho dne (--date=MM-DD)';
	}


	public function run(array $options, Output $output): int
	{
		$date = $options['date'] ?? null;
		if (!is_string($date) || array_diff(array_keys($options), ['date']) !== []) {
			throw new UsageException('Použití: import:day --date=MM-DD (např. --date=10-28)');
		}
		try {
			$day = MonthDay::fromString($date);
		} catch (\InvalidArgumentException) {
			throw new UsageException("Neplatné datum „{$date}“, očekávám MM-DD (např. 10-28)");
		}

		try {
			$output->writeln(self::describe($this->dayImport->import($day)));
			return self::ExitOk;
		} catch (ImportFailedException $e) {
			$output->error($e->getMessage());
			return self::ExitFailure;
		}
	}


	public static function describe(DayImportResult $result): string
	{
		return sprintf(
			'%s ok (revize %d): Česko %d, Svět %d | nové %d, upravené %d, beze změny %d, zmizelé %d, k revizi %d',
			$result->day->format(),
			$result->revisionId,
			$result->czechCount,
			$result->worldCount,
			$result->inserted,
			$result->updated,
			$result->unchanged,
			$result->missing,
			$result->review,
		);
	}
}
