<?php

declare(strict_types=1);

namespace App\Model\Import;


/**
 * The introduction of a Wikipedia article (REST page/summary) of an exact revision.
 */
final readonly class ArticleSummary
{
	public function __construct(
		public string $title,
		public string $extract,
		public int $revisionId,
		public \DateTimeImmutable $fetchedAt,
	) {
	}
}
