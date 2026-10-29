<?php

declare(strict_types=1);

namespace App\Model\Wikipedia;


/**
 * Links to an exact revision (traceability) and to the history (authors, CC BY-SA attribution),
 * on cs.wikipedia or sk.wikipedia.
 */
final class WikipediaUrl
{
	public static function revision(string $title, int $revisionId, string $language = 'cs'): string
	{
		return self::base($language) . self::encodeTitle($title) . '&oldid=' . $revisionId;
	}


	public static function history(string $title, string $language = 'cs'): string
	{
		return self::base($language) . self::encodeTitle($title) . '&action=history';
	}


	public static function article(string $title, string $language = 'cs'): string
	{
		return "https://{$language}.wikipedia.org/wiki/" . self::encodeTitle($title);
	}


	private static function base(string $language): string
	{
		if (!in_array($language, ['cs', 'sk'], true)) {
			throw new \InvalidArgumentException("Unsupported Wikipedia language '$language'");
		}
		return "https://{$language}.wikipedia.org/w/index.php?title=";
	}


	private static function encodeTitle(string $title): string
	{
		return rawurlencode(str_replace(' ', '_', $title));
	}
}
