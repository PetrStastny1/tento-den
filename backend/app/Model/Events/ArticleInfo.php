<?php

declare(strict_types=1);

namespace App\Model\Events;


/**
 * An existing Wikipedia article (redirects already resolved).
 */
final readonly class ArticleInfo
{
	public function __construct(
		public int $pageId,
		public string $title,
		public int $revisionId,
		public ?string $wikibaseItem,
	) {
	}
}
