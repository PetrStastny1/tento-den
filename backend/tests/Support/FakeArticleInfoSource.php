<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Model\Events\ArticleInfo;
use App\Model\Import\ArticleInfoSource;


/**
 * Every title is an existing article with a stable fake page id; redirects can be configured.
 */
final class FakeArticleInfoSource implements ArticleInfoSource
{
	/**
	 * @param array<string, string> $redirects from => to
	 */
	public function __construct(
		private readonly array $redirects = [],
	) {
	}


	public function fetch(array $titles): array
	{
		$result = [];
		foreach ($titles as $title) {
			$target = $this->redirects[$title] ?? $title;
			$result[$title] = new ArticleInfo(self::pageId($target), $target, 1000, 'Q' . self::pageId($target));
		}
		return $result;
	}


	public static function pageId(string $title): int
	{
		return crc32($title) & 0x7FFFFFFF;
	}
}
