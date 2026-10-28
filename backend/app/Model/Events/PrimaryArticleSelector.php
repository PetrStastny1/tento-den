<?php

declare(strict_types=1);

namespace App\Model\Events;


/**
 * The primary article of an event is the first linked article that is not generic
 * (Praha, USA, Druhá světová válka…). Years and dates are filtered out earlier by LinkFilter.
 * The generic list is configured by hand (config/generic-articles.neon); link frequency
 * only suggests candidates and never changes the list automatically.
 */
final class PrimaryArticleSelector
{
	/** @var array<string, true> */
	private readonly array $generic;


	/**
	 * @param list<string> $genericTitles
	 */
	public function __construct(array $genericTitles)
	{
		$this->generic = array_fill_keys($genericTitles, true);
	}


	/**
	 * @param list<ArticleInfo> $articles in text order, redirects resolved
	 */
	public function select(array $articles): ?ArticleInfo
	{
		foreach ($articles as $article) {
			if (!$this->isGeneric($article->title)) {
				return $article;
			}
		}
		return null;
	}


	public function isGeneric(string $title): bool
	{
		return isset($this->generic[$title]);
	}
}
