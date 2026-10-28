<?php

declare(strict_types=1);

namespace App\Model\Events;


/**
 * Decides which linked pages can be the article of an event: years, dates,
 * "year in topic" pages, centuries, decades and other namespaces cannot.
 */
final class LinkFilter
{
	private const Months = 'leden|únor|březen|duben|květen|červen|červenec|srpen|září|říjen|listopad|prosinec';

	private const Excluded = [
		'~^\d{1,4}( př\. n\. l\.)?$~u',                       // 1918, 45 př. n. l.
		'~^\d{1,2}\. (' . self::Months . ')$~u',               // 28. říjen
		'~^\d{1,4} v ~u',                                      // 1984 v dopravě
		'~^\d{1,2}\. (století|tisíciletí)( př\. n\. l\.)?$~u', // 20. století
		'~^\d{1,2}\. léta~u',                                  // 60. léta 20. století
		'~^(Soubor|File|Kategorie|Category|Wikipedie|Šablona|Portál|Nápověda|Speciální):~u',
	];


	public function isArticleCandidate(string $title): bool
	{
		foreach (self::Excluded as $pattern) {
			if (preg_match($pattern, $title)) {
				return false;
			}
		}
		return true;
	}
}
