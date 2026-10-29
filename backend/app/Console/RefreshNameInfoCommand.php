<?php

declare(strict_types=1);

namespace App\Console;

use App\Core\Console\Command;
use App\Core\Console\Output;
use App\Core\Console\UsageException;
use App\Model\Import\SourceException;
use App\Model\NameDays\Country;
use App\Model\NameDays\NameInfoService;


final class RefreshNameInfoCommand implements Command
{
	public function __construct(
		private readonly NameInfoService $names,
	) {
	}


	public function getName(): string
	{
		return 'names:refresh';
	}


	public function getDescription(): string
	{
		return 'Dohledá význam jmen a známé nositele (--country=cz|sk, jen chybějící, --all všechna)';
	}


	public function run(array $options, Output $output): int
	{
		$country = Country::tryFrom(is_string($options['country'] ?? null) ? $options['country'] : '');
		if ($country === null || array_diff(array_keys($options), ['country', 'all']) !== [] || !in_array($options['all'] ?? true, [true], true)) {
			throw new UsageException('Použití: names:refresh --country=cz|sk [--all]');
		}
		try {
			$result = $this->names->refresh($country, isset($options['all']), fn(int $done, int $total) => $output->writeln("Jména ({$country->value}): $done / $total"));
		} catch (SourceException $e) {
			$output->error('Dohledání selhalo (uložená jména zůstávají): ' . $e->getMessage());
			return self::ExitFailure;
		}
		$output->writeln(sprintf('Hotovo: %d jmen, význam %d, nositel %d.', $result['names'], $result['meaning'], $result['bearer']));
		return self::ExitOk;
	}
}
