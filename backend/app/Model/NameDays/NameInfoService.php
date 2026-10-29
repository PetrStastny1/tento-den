<?php

declare(strict_types=1);

namespace App\Model\NameDays;

use App\Core\Clock\Clock;
use App\Model\Import\ArticleSummarySource;
use App\Model\Import\SourceException;


/**
 * Looks up the meaning (article introduction) and a known bearer of the names shown in a mode, from
 * the Wikipedia and citizenship of that mode. Server only (console), in batches; nothing is added
 * when nothing is found.
 */
final class NameInfoService
{
	private const BatchSize = 20;


	public function __construct(
		private readonly NameDayRepository $nameDays,
		private readonly NameInfoRepository $infos,
		private readonly NameArticleSource $articles,
		private readonly ArticleSummarySource $csSummaries,
		private readonly ArticleSummarySource $skSummaries,
		private readonly BearerSource $bearers,
		private readonly NameBearerExclusions $exclusions,
		private readonly Clock $clock,
	) {
	}


	/**
	 * @param \Closure(int $done, int $total): void|null $progress
	 * @return array{names: int, meaning: int, bearer: int}
	 * @throws SourceException
	 */
	public function refresh(Country $country, bool $all, ?\Closure $progress = null): array
	{
		$names = $this->nameDays->mainNames($country);
		if (!$all) {
			$known = $this->infos->findByNames($country, $names);
			$names = array_values(array_filter($names, fn(string $name): bool => !isset($known[$name])));
		}

		$summaries = $country === Country::Slovak ? $this->skSummaries : $this->csSummaries;
		$result = ['names' => 0, 'meaning' => 0, 'bearer' => 0];
		foreach (array_chunk($names, self::BatchSize) as $batch) {
			foreach ($this->articles->find($batch, $country) as $name => $article) {
				$meaning = $article === null ? null : $summaries->fetch($article->title);
				$bearer = $article === null ? null : $this->bearers->find($article->wikidataItem, $name, $country, $this->exclusions->forName($country, $name));
				$this->infos->save(new NameInfo($name, $country, $article?->wikidataItem, $meaning, $bearer, $this->clock->now()));
				$result['names']++;
				$result['meaning'] += (int) ($meaning !== null);
				$result['bearer'] += (int) ($bearer !== null);
			}
			if ($progress !== null) {
				$progress($result['names'], count($names));
			}
		}
		return $result;
	}
}
