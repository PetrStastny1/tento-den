<?php

declare(strict_types=1);

namespace App\Console;

use App\Core\Console\Command;
use App\Core\Console\Output;
use App\Core\Console\UsageException;
use App\Model\Import\SignalRefreshService;
use App\Model\Import\SourceException;


final class RefreshSignalsCommand implements Command
{
	public function __construct(
		private readonly SignalRefreshService $signals,
	) {
	}


	public function getName(): string
	{
		return 'articles:refresh-signals';
	}


	public function getDescription(): string
	{
		return 'Stáhne počet jazykových verzí článků z Wikidat (jen chybějící, --all všechny)';
	}


	public function run(array $options, Output $output): int
	{
		if (array_diff(array_keys($options), ['all']) !== [] || !in_array($options['all'] ?? true, [true], true)) {
			throw new UsageException('Použití: articles:refresh-signals [--all]');
		}
		try {
			$result = $this->signals->refresh(
				isset($options['all']),
				fn(int $done, int $total) => $output->writeln("Signály: $done / $total"),
			);
		} catch (SourceException $e) {
			$output->error('Stažení signálů selhalo (uložené dávky zůstávají): ' . $e->getMessage());
			return self::ExitFailure;
		}
		$output->writeln(sprintf('Hotovo: %d položek Wikidat, nenalezeno %d.', $result['items'], $result['notFound']));
		return self::ExitOk;
	}
}
