<?php

declare(strict_types=1);

namespace App\Console;

use App\Core\Console\Command;
use App\Core\Console\Output;
use App\Core\Console\UsageException;
use App\Model\Import\SourceException;
use App\Model\StateDays\StateDayArticleService;


final class RefreshStateDaysCommand implements Command
{
	public function __construct(
		private readonly StateDayArticleService $articles,
	) {
	}


	public function getName(): string
	{
		return 'state-days:refresh';
	}


	public function getDescription(): string
	{
		return 'Stáhne úvody článků o svátcích a významných dnech bez historické události (REST page/summary)';
	}


	public function run(array $options, Output $output): int
	{
		if ($options !== []) {
			throw new UsageException('Použití: state-days:refresh');
		}
		try {
			$result = $this->articles->refresh();
		} catch (SourceException $e) {
			$output->error('Stažení úvodů selhalo (uložené zůstávají): ' . $e->getMessage());
			return self::ExitFailure;
		}
		foreach ($result['missing'] as $title) {
			$output->error("Článek „{$title}“ neexistuje.");
		}
		$output->writeln(sprintf('Uloženo %d úvodů, chybí %d.', $result['saved'], count($result['missing'])));
		return $result['missing'] === [] ? self::ExitOk : self::ExitFailure;
	}
}
