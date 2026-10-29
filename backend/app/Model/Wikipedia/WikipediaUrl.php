<?php

declare(strict_types=1);

namespace App\Model\Wikipedia;


/**
 * Links to an exact revision (traceability) and to the history (authors, CC BY-SA attribution).
 */
final class WikipediaUrl
{
	private const Base = 'https://cs.wikipedia.org/w/index.php?title=';


	public static function revision(string $title, int $revisionId): string
	{
		return self::Base . self::encodeTitle($title) . '&oldid=' . $revisionId;
	}


	public static function history(string $title): string
	{
		return self::Base . self::encodeTitle($title) . '&action=history';
	}


	private static function encodeTitle(string $title): string
	{
		return rawurlencode(str_replace(' ', '_', $title));
	}
}
