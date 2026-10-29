<?php

declare(strict_types=1);

namespace App\Console;

use App\Api\Dto\DayResponse;
use App\Api\Dto\HolidayResponse;
use App\Core\Console\Command;
use App\Core\Console\Output;
use App\Core\Console\UsageException;
use App\Model\Events\MonthDay;
use App\Model\Selection\DayService;
use App\Model\StateDays\StateDayCalendar;
use Nette\Utils\FileSystem;
use Nette\Utils\Json;


/**
 * Writes the ten cards (and holidays without a year) of all 366 days as static JSON files MM-DD.json,
 * plus movable.json with the movable holidays by date – the same format
 * as GET /api/day – for a demo without a backend.
 */
final class ExportStaticCommand implements Command
{
	private const MovableYearsAhead = 10;


	public function __construct(
		private readonly DayService $dayService,
		private readonly StateDayCalendar $stateDays,
	) {
	}


	public function getName(): string
	{
		return 'export:static';
	}


	public function getDescription(): string
	{
		return 'Exportuje výběr (10 karet) všech 366 dní jako statické JSON (--dir=temp/export)';
	}


	public function run(array $options, Output $output): int
	{
		$dir = $options['dir'] ?? 'temp/export';
		if (!is_string($dir) || $dir === '' || array_diff(array_keys($options), ['dir']) !== []) {
			throw new UsageException('Použití: export:static [--dir=ADRESÁŘ]');
		}

		FileSystem::createDir($dir);
		$cards = $holidays = 0;
		foreach (MonthDay::all() as $day) {
			$content = $this->dayService->day($day); // without a year: no movable holidays
			$cards += count($content->cards);
			$holidays += count($content->holidays);
			FileSystem::write("$dir/{$day->format()}.json", Json::encode(new DayResponse($day, $content)) . "\n");
		}

		// movable holidays (Easter) by exact date, for the frontend to add when it shows today
		$movable = [];
		$currentYear = $this->dayService->currentYear();
		for ($year = $currentYear - 1; $year <= $currentYear + self::MovableYearsAhead; $year++) {
			foreach ($this->stateDays->all() as $stateDay) {
				if ($stateDay->easterOffset === null) {
					continue;
				}
				$date = $stateDay->dateIn($year);
				$content = $this->dayService->day($date, $year);
				foreach ($content->holidays as $holiday) {
					if ($holiday->stateDay === $stateDay) {
						$movable[sprintf('%d-%s', $year, $date->format())][] = new HolidayResponse($holiday, $date);
					}
				}
			}
		}
		ksort($movable);
		FileSystem::write("$dir/movable.json", Json::encode($movable) . "\n");

		$output->writeln(sprintf('Exportováno 366 dní, %d karet, %d karet svátků a %d dat pohyblivých svátků do %s', $cards, $holidays, count($movable), $dir));
		return self::ExitOk;
	}
}
