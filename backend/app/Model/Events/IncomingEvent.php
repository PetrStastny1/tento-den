<?php

declare(strict_types=1);

namespace App\Model\Events;


/**
 * A parsed event with resolved articles, ready to be matched and stored.
 */
final readonly class IncomingEvent
{
	public string $textHash;


	/**
	 * @param list<ArticleInfo> $articles existing linked articles in text order, without duplicates
	 */
	public function __construct(
		public ParsedEvent $parsed,
		public array $articles,
		public ?ArticleInfo $primaryArticle,
	) {
		$this->textHash = EventText::hash($parsed->text);
	}


	public function groupKey(): string
	{
		return $this->parsed->section->value . '|' . $this->parsed->year;
	}
}
