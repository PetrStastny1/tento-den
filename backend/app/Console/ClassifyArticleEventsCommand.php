<?php

declare(strict_types=1);

namespace App\Console;

use App\Core\Console\Command;
use App\Core\Console\Output;
use App\Core\Console\UsageException;
use App\Model\Anniversaries\ArticleEventService;
use App\Model\Import\SourceException;


final class ClassifyArticleEventsCommand implements Command
{
	public function __construct(
		private readonly ArticleEventService $events,
	) {
	}


	public function getName(): string
	{
		return 'articles:classify-events';
	}


	public function getDescription(): string
	{
		return 'Zjistí z Wikidat, které hlavní články jsou samy velkou událostí, a jejich datum (jen nezjištěné, --all všechny)';
	}


	public function run(array $options, Output $output): int
	{
		if (array_diff(array_keys($options), ['all']) !== [] || !in_array($options['all'] ?? true, [true], true)) {
			throw new UsageException('Použití: articles:classify-events [--all]');
		}
		try {
			$result = $this->events->refresh(isset($options['all']));
		} catch (SourceException $e) {
			$output->error('Zjištění selhalo (uložené zůstávají): ' . $e->getMessage());
			return self::ExitFailure;
		}
		$output->writeln(sprintf('Hotovo: %d položek Wikidat, z toho %d velkých událostí.', $result['items'], $result['events']));
		return self::ExitOk;
	}
}
