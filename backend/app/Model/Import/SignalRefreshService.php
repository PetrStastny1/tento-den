<?php

declare(strict_types=1);

namespace App\Model\Import;

use App\Core\Clock\Clock;
use App\Core\Database\Transaction;
use App\Model\Events\ArticleRepository;


/**
 * Refreshes article signals (Wikidata sitelinks) in batches; each batch is stored at once,
 * so an interrupted run keeps what it already fetched and continues with the rest.
 */
final class SignalRefreshService
{
	private const BatchSize = 500;


	public function __construct(
		private readonly ArticleRepository $articles,
		private readonly SitelinksSource $sitelinks,
		private readonly Transaction $transaction,
		private readonly Clock $clock,
	) {
	}


	/**
	 * @param \Closure(int $done, int $total): void $progress
	 * @return array{items: int, notFound: int}
	 * @throws SourceException
	 */
	public function refresh(bool $all, \Closure $progress): array
	{
		$items = $this->articles->itemsForSignals($all);
		$notFound = 0;
		foreach (array_chunk($items, self::BatchSize) as $index => $batch) {
			$counts = $this->sitelinks->fetch($batch);
			$notFound += count(array_filter($counts, fn(?int $count): bool => $count === null));
			$this->transaction->run(fn() => $this->articles->updateSitelinks($counts, $this->clock->now()));
			$progress(min(($index + 1) * self::BatchSize, count($items)), count($items));
		}
		return ['items' => count($items), 'notFound' => $notFound];
	}
}
