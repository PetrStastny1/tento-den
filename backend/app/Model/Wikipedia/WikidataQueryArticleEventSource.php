<?php

declare(strict_types=1);

namespace App\Model\Wikipedia;

use App\Core\Http\HttpClient;
use App\Core\Http\HttpException;
use App\Model\Anniversaries\ArticleEventSource;
use App\Model\Import\SourceException;
use Nette\Utils\Json;
use Nette\Utils\JsonException;


/**
 * Event class and date of articles from the Wikidata Query Service, in batches.
 */
final class WikidataQueryArticleEventSource implements ArticleEventSource
{
	private const Endpoint = 'https://query.wikidata.org/sparql';
	private const BatchSize = 250;


	public function __construct(
		private readonly HttpClient $http,
	) {
	}


	public function classify(array $items, array $classes): array
	{
		$result = [];
		$valid = fn(string $q): bool => (bool) preg_match('~^Q\d+$~', $q);
		if (array_filter([...$items, ...$classes], fn(string $q): bool => !$valid($q)) !== []) {
			throw new \InvalidArgumentException('Invalid Wikidata item');
		}
		$values = fn(array $qs): string => implode(' ', array_map(fn(string $q): string => "wd:$q", $qs));
		foreach (array_chunk($items, self::BatchSize) as $batch) {
			foreach ($batch as $item) {
				$result[$item] = ['class' => null, 'years' => []];
			}
			$found = [];
			foreach ($this->query("SELECT ?item ?class WHERE { VALUES ?item { {$values($batch)} } VALUES ?class { {$values($classes)} } ?item wdt:P31/wdt:P279* ?class . }") as $row) {
				$found[$this->id($row['item'])][] = $this->id($row['class']);
			}
			foreach ($found as $item => $itemClasses) {
				if (isset($result[$item])) {
					// the first class in configured order, so the reason is stable
					$result[$item] = ['class' => array_values(array_intersect($classes, $itemClasses))[0] ?? null, 'years' => $result[$item]['years']];
				}
			}
			foreach ($this->query("SELECT ?item ?date WHERE { VALUES ?item { {$values($batch)} } VALUES ?p { wdt:P585 wdt:P580 wdt:P571 } ?item ?p ?date . }") as $row) {
				if (preg_match('~^(-?)0*(\d+)-~', $row['date'], $m)) {
					$year = (int) ($m[1] . $m[2]);
					// XSD dates count a year 0 (= 1 BC); the events have no year 0
					$year = $year <= 0 ? $year - 1 : $year;
					$item = $this->id($row['item']);
					if (isset($result[$item])) {
						$result[$item] = ['class' => $result[$item]['class'], 'years' => array_values(array_unique([...$result[$item]['years'], $year]))];
					}
				}
			}
		}
		return $result;
	}


	/**
	 * @return list<array<string, string>>
	 * @throws SourceException
	 */
	private function query(string $sparql): array
	{
		try {
			$response = $this->http->get(self::Endpoint . '?' . http_build_query(['query' => $sparql, 'format' => 'json']), ['Accept' => 'application/sparql-results+json']);
		} catch (HttpException $e) {
			throw new SourceException($e->getMessage(), 0, $e);
		}
		if ($response->status !== 200) {
			throw new SourceException("Wikidata Query Service: HTTP {$response->status}");
		}
		try {
			$data = Json::decode($response->body, forceArrays: true);
		} catch (JsonException $e) {
			throw new SourceException('Wikidata Query Service: invalid JSON', 0, $e);
		}
		$rows = [];
		foreach ($data['results']['bindings'] ?? [] as $binding) {
			$rows[] = array_map(fn(array $value): string => (string) $value['value'], $binding);
		}
		return $rows;
	}


	private function id(string $uri): string
	{
		return substr($uri, (int) strrpos($uri, '/') + 1);
	}
}
