<?php

declare(strict_types=1);

namespace App\Model\Wikipedia;

use App\Model\Import\SourceException;
use App\Model\NameDays\Country;
use App\Model\NameDays\NameArticle;
use App\Model\NameDays\NameArticleSource;


/**
 * The article about a name: the first existing candidate title („Josef (jméno)“, „Josef“ on
 * cs.wikipedia; „Jozef (prvé meno)“, „Jozef (meno)“, „Jozef“ on sk.wikipedia, redirects followed)
 * that is not a disambiguation page and whose Wikidata item is an instance of a given name. „Sibyla“ (the prophetess) is skipped for
 * „Sibyla (jméno)“; a name with only an article about a person or a place gets nothing.
 */
final class ActionApiNameArticleSource implements NameArticleSource
{
	private const TitleBatch = 50;
	private const GivenNameClasses = [
		'Q202444',   // given name
		'Q12308941', // male given name
		'Q11879590', // female given name
		'Q3409032',  // unisex given name
	];


	public function __construct(
		private readonly ActionApiClient $csApi,
		private readonly ActionApiClient $skApi,
		private readonly ActionApiClient $wikidataApi,
	) {
	}


	public function find(array $names, Country $country): array
	{
		$candidates = [];
		foreach ($names as $name) {
			$candidates[$name] = $country === Country::Slovak
				? ["$name (prvé meno)", "$name (meno)", $name]
				: ["$name (jméno)", $name];
		}

		$items = $this->items(array_merge(...array_values($candidates)), $country === Country::Slovak ? $this->skApi : $this->csApi);
		$givenNames = $this->givenNames(array_values(array_unique(array_filter(array_column($items, 'item')))));

		$result = [];
		foreach ($candidates as $name => $titles) {
			$result[$name] = null;
			foreach ($titles as $title) {
				$item = $items[$title] ?? null;
				if ($item !== null && isset($givenNames[$item['item']])) {
					$result[$name] = new NameArticle($item['title'], $item['item']);
					break;
				}
			}
		}
		return $result;
	}


	/**
	 * @param list<string> $titles
	 * @return array<string, array{title: string, item: string}> requested title => resolved article and its item
	 * @throws SourceException
	 */
	private function items(array $titles, ActionApiClient $api): array
	{
		$result = [];
		foreach (array_chunk($titles, self::TitleBatch) as $batch) {
			$data = $api->query(['action' => 'query', 'prop' => 'pageprops', 'ppprop' => 'wikibase_item|disambiguation', 'redirects' => '1', 'titles' => implode('|', $batch)]);
			$query = $data['query'] ?? null;
			if (!is_array($query)) {
				throw new SourceException('Action API: response without query');
			}
			$map = [];
			foreach ([...($query['normalized'] ?? []), ...($query['redirects'] ?? [])] as $step) {
				$map[(string) $step['from']] = (string) $step['to'];
			}
			$pages = [];
			foreach ($query['pages'] ?? [] as $page) {
				// a disambiguation page („Albrecht“) can carry the item of the name, but it is not an article about it
				if (is_array($page) && !isset($page['missing']) && !isset($page['pageprops']['disambiguation']) && is_string($page['pageprops']['wikibase_item'] ?? null)) {
					$pages[(string) $page['title']] = $page['pageprops']['wikibase_item'];
				}
			}
			foreach ($batch as $title) {
				$resolved = $title;
				for ($i = 0; $i < 3 && isset($map[$resolved]); $i++) {
					$resolved = $map[$resolved]; // normalized, then redirected
				}
				if (isset($pages[$resolved])) {
					$result[$title] = ['title' => $resolved, 'item' => $pages[$resolved]];
				}
			}
		}
		return $result;
	}


	/**
	 * @param list<string> $items
	 * @return array<string, true> items that are an instance of a given name
	 * @throws SourceException
	 */
	private function givenNames(array $items): array
	{
		$result = [];
		foreach (array_chunk($items, self::TitleBatch) as $batch) {
			$data = $this->wikidataApi->query(['action' => 'wbgetentities', 'props' => 'claims', 'ids' => implode('|', $batch)]);
			foreach ($data['entities'] ?? [] as $id => $entity) {
				foreach ($entity['claims']['P31'] ?? [] as $claim) {
					if (in_array($claim['mainsnak']['datavalue']['value']['id'] ?? null, self::GivenNameClasses, true)) {
						$result[(string) $id] = true;
					}
				}
			}
		}
		return $result;
	}
}
