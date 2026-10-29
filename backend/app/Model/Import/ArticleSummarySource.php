<?php

declare(strict_types=1);

namespace App\Model\Import;



interface ArticleSummarySource
{
	/**
	 * @return ArticleSummary|null null when the article does not exist
	 * @throws SourceException
	 */
	public function fetch(string $title): ?ArticleSummary;
}
