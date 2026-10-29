<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Model\Import\ArticleSummary;
use App\Model\Import\ArticleSummarySource;


/**
 * Every article exists with a one-sentence introduction and a fixed revision.
 */
final class FakeArticleSummarySource implements ArticleSummarySource
{
	public function fetch(string $title): ArticleSummary
	{
		return new ArticleSummary($title, "{$title} je svátek.", 123456, new \DateTimeImmutable('2026-10-27 23:00:00'));
	}
}
