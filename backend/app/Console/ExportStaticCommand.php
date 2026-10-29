<?php

declare(strict_types=1);

namespace App\Console;

use App\Api\Dto\DayResponse;
use App\Core\Console\Command;
use App\Core\Console\Output;
use App\Core\Console\UsageException;
use App\Model\Events\MonthDay;
use App\Model\Selection\DayService;
use Nette\Utils\FileSystem;
use Nette\Utils\Json;


/**
 * Writes the five cards of all 366 days as static JSON files MM-DD.json – the same format
 * as GET /api/day – for a demo without a backend.
 */
final class ExportStaticCommand implements Command
{
	public function __construct(
		private readonly DayService $dayService,
	) {
	}


	public function getName(): string
	{
		return 'export:static';
	}


	public function getDescription(): string
	{
		return 'Exportuje pětice všech 366 dní jako statické JSON (--dir=temp/export)';
	}


	public function run(array $options, Output $output): int
	{
		$dir = $options['dir'] ?? 'temp/export';
		if (!is_string($dir) || $dir === '' || array_diff(array_keys($options), ['dir']) !== []) {
			throw new UsageException('Použití: export:static [--dir=ADRESÁŘ]');
		}

		FileSystem::createDir($dir);
		$cards = 0;
		foreach (MonthDay::all() as $day) {
			$dayCards = $this->dayService->cardsForDay($day);
			$cards += count($dayCards);
			FileSystem::write("$dir/{$day->format()}.json", Json::encode(new DayResponse($day, $dayCards)) . "\n");
		}
		$output->writeln(sprintf('Exportováno 366 dní, %d karet do %s', $cards, $dir));
		return self::ExitOk;
	}
}
