<?php

declare(strict_types=1);

namespace App\Model\Anniversaries;

use App\Core\Clock\Clock;
use App\Model\Import\SourceException;


/**
 * Marks linked articles that are themselves events of a big type, with the years of their date
 * (server only, console; like the sitelinks signal).
 */
final class ArticleEventService
{
	public function __construct(
		private readonly AnniversaryConfig $config,
		private readonly ArticleEventSource $source,
		private readonly ArticleEventRepository $articles,
		private readonly Clock $clock,
	) {
	}


	/**
	 * @return array{items: int, events: int}
	 * @throws SourceException
	 */
	public function refresh(bool $all): array
	{
		$result = ['items' => 0, 'events' => 0];
		foreach (array_chunk($this->articles->linkedItems($all), 250) as $batch) {
			foreach ($this->source->classify($batch, array_keys($this->config->eventClasses)) as $item => $event) {
				$this->articles->save($item, $event['class'], $event['years'], $this->clock->now());
				$result['items']++;
				$result['events'] += (int) ($event['class'] !== null);
			}
		}
		return $result;
	}
}
