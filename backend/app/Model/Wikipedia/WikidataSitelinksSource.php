<?php

declare(strict_types=1);

namespace App\Model\Wikipedia;

use App\Model\Import\SitelinksSource;
use App\Model\Import\SourceException;


/**
 * Counts Wikipedia sitelinks of Wikidata items, 50 per request (wbgetentities, props=sitelinks).
 * One unknown id fails the whole request ("no-such-entity" with its id), so it is recorded
 * as null and the rest of the batch is asked again.
 */
final class WikidataSitelinksSource implements SitelinksSource
{
	private const BatchSize = 50;
	/** sitelinks of Wikimedia projects that are not language editions of Wikipedia */
	private const NonWikipedia = ['commonswiki', 'specieswiki', 'metawiki', 'wikidatawiki', 'mediawikiwiki', 'sourceswiki', 'wikimaniawiki', 'incubatorwiki', 'outreachwiki', 'wikifunctionswiki'];


	public function __construct(
		private readonly ActionApiClient $wikidataApi,
	) {
	}


	public function fetch(array $items): array
	{
		$result = [];
		foreach (array_chunk(array_values(array_unique($items)), self::BatchSize) as $batch) {
			$result += $this->fetchBatch($batch);
		}
		return $result;
	}


	/**
	 * @param list<string> $batch
	 * @return array<string, int|null>
	 */
	private function fetchBatch(array $batch): array
	{
		$result = [];
		while ($batch !== []) {
			try {
				$data = $this->wikidataApi->query(['action' => 'wbgetentities', 'props' => 'sitelinks', 'ids' => implode('|', $batch)]);
			} catch (ActionApiErrorException $e) {
				$missing = $e->error['id'] ?? null;
				if ($e->errorCode !== 'no-such-entity' || !is_string($missing) || !in_array($missing, $batch, true)) {
					throw $e;
				}
				$result[$missing] = null;
				$batch = array_values(array_diff($batch, [$missing]));
				continue;
			}

			$entities = $data['entities'] ?? null;
			if (!is_array($entities)) {
				throw new SourceException('Wikidata: response without entities');
			}
			foreach ($batch as $item) {
				$entity = $entities[$item] ?? null;
				$result[$item] = is_array($entity) && !isset($entity['missing']) ? $this->countWikipedias($entity['sitelinks'] ?? []) : null;
			}
			break;
		}
		return $result;
	}


	/**
	 * @param array<string, mixed> $sitelinks
	 */
	private function countWikipedias(array $sitelinks): int
	{
		$count = 0;
		foreach (array_keys($sitelinks) as $site) {
			if (preg_match('~^[a-z_]+wiki$~', $site) && !in_array($site, self::NonWikipedia, true)) {
				$count++;
			}
		}
		return $count;
	}
}
