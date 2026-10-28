<?php

declare(strict_types=1);

namespace App\Model\Wikipedia;

use App\Model\Events\ArticleInfo;
use App\Model\Import\ArticleInfoSource;
use App\Model\Import\SourceException;


/**
 * Page id, current revision and Wikidata item for up to 50 titles per request;
 * redirects are resolved (Husité → Husitství).
 */
final class ActionApiArticleInfoSource implements ArticleInfoSource
{
	private const BatchSize = 50;
	private const ArticleNamespace = 0;


	public function __construct(
		private readonly ActionApiClient $actionApi,
	) {
	}


	public function fetch(array $titles): array
	{
		$result = [];
		foreach (array_chunk(array_values(array_unique($titles)), self::BatchSize) as $batch) {
			$result += $this->fetchBatch($batch);
		}
		return $result;
	}


	/**
	 * @param list<string> $titles
	 * @return array<string, ArticleInfo|null>
	 */
	private function fetchBatch(array $titles): array
	{
		$aliases = $pages = [];
		$continue = [];
		do {
			$data = $this->actionApi->query([
				'action' => 'query',
				'prop' => 'info|pageprops',
				'ppprop' => 'wikibase_item',
				'redirects' => '1',
				'titles' => implode('|', $titles),
			] + $continue);

			$query = $data['query'] ?? null;
			if (!is_array($query) || !is_array($query['pages'] ?? null)) {
				throw new SourceException('Action API: response without query.pages');
			}
			foreach (['normalized', 'redirects'] as $key) {
				foreach ($query[$key] ?? [] as $alias) {
					$aliases[$alias['from']] = $alias['to'];
				}
			}
			foreach ($query['pages'] as $page) {
				$pages[$page['title']] = ($pages[$page['title']] ?? []) + $page;
			}
			$continue = is_array($data['continue'] ?? null) ? $data['continue'] : [];
		} while ($continue !== []);

		$result = [];
		foreach ($titles as $title) {
			$result[$title] = $this->toArticle($pages[$this->resolve($title, $aliases)] ?? null);
		}
		return $result;
	}


	/**
	 * @param array<string, string> $aliases
	 */
	private function resolve(string $title, array $aliases): string
	{
		for ($i = 0; $i < 3 && isset($aliases[$title]); $i++) { // normalization, then redirect
			$title = $aliases[$title];
		}
		return $title;
	}


	/**
	 * @param array<string, mixed>|null $page
	 */
	private function toArticle(?array $page): ?ArticleInfo
	{
		if ($page === null || isset($page['missing']) || isset($page['invalid']) || ($page['ns'] ?? null) !== self::ArticleNamespace) {
			return null;
		}
		if (!is_int($page['pageid'] ?? null) || !is_int($page['lastrevid'] ?? null) || !is_string($page['title'] ?? null)) {
			throw new SourceException('Action API: incomplete page info for ' . json_encode($page['title'] ?? null));
		}
		$item = $page['pageprops']['wikibase_item'] ?? null;
		return new ArticleInfo($page['pageid'], $page['title'], $page['lastrevid'], is_string($item) ? $item : null);
	}
}
