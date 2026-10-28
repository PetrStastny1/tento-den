<?php

declare(strict_types=1);

namespace App\Console;

use App\Core\Console\Command;
use App\Core\Console\Output;
use App\Core\Console\UsageException;
use App\Model\Events\ArticleRepository;
use App\Model\Events\PrimaryArticleSelector;


/**
 * Lists frequently linked articles as candidates for config/generic-articles.neon.
 * Only a suggestion: the list is edited by hand, nothing is marked automatically.
 */
final class GenericCandidatesCommand implements Command
{
	public function __construct(
		private readonly ArticleRepository $articles,
		private readonly PrimaryArticleSelector $primarySelector,
	) {
	}


	public function getName(): string
	{
		return 'articles:generic-candidates';
	}


	public function getDescription(): string
	{
		return 'Vypíše často odkazované články – kandidáty na seznam obecných (--min=20)';
	}


	public function run(array $options, Output $output): int
	{
		$min = $options['min'] ?? '20';
		if (!is_string($min) || !ctype_digit($min) || array_diff(array_keys($options), ['min']) !== []) {
			throw new UsageException('Použití: articles:generic-candidates [--min=POČET]');
		}

		$output->writeln('Počet událostí  Článek  (* = už je v seznamu obecných)');
		foreach ($this->articles->linkFrequencies((int) $min) as $row) {
			$mark = $this->primarySelector->isGeneric($row['title']) ? '*' : ' ';
			$output->writeln(sprintf('%14d  %s %s', $row['events'], $mark, $row['title']));
		}
		return self::ExitOk;
	}
}
