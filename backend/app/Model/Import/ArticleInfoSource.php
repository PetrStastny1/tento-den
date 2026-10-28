<?php

declare(strict_types=1);

namespace App\Model\Import;

use App\Model\Events\ArticleInfo;


interface ArticleInfoSource
{
	/**
	 * Resolves titles (following redirects) to articles.
	 * @param list<string> $titles
	 * @return array<string, ArticleInfo|null> requested title => article, null when it does not exist
	 * @throws SourceException
	 */
	public function fetch(array $titles): array;
}
