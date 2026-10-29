<?php

declare(strict_types=1);

namespace App\Model\StateDays;

use App\Model\Import\SourceException;


interface ArticleSummarySource
{
	/**
	 * @return ArticleSummary|null null when the article does not exist
	 * @throws SourceException
	 */
	public function fetch(string $title): ?ArticleSummary;
}
