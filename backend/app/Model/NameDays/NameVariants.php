<?php

declare(strict_types=1);

namespace App\Model\NameDays;


/**
 * Which names of a day are variants of another name of the same day – only from data, never by the
 * similarity of letters:
 * a) the article about the name redirects to the article of another name of the day („Sylva“ → „Silvie“),
 * b) both names have the same Wikidata item,
 * c) their items are linked by „said to be the same as“ (P460) or „given name for the other gender“ (P1560).
 * The main name is the one earlier on the page. Otherwise the names stand alone (Petr and Pavel).
 */
final class NameVariants
{
	/**
	 * @param list<string> $names the names of the day in page order (all lines)
	 * @param array<string, NameInfo> $infos what is known about the names
	 * @return array<string, array{name: string, rule: string}> variant => main name and rule (a, b, c)
	 */
	public static function find(array $names, array $infos): array
	{
		$variants = [];
		foreach ($names as $index => $name) {
			$info = $infos[$name] ?? null;
			if ($info === null) {
				continue;
			}
			$about = $info->meaning === null ? null : self::nameOfArticle($info->meaning->title);
			foreach (array_slice($names, 0, $index) as $earlier) {
				$main = $infos[$earlier] ?? null;
				$rule = match (true) {
					$about !== null && $about !== $name && $about === $earlier => 'a',
					$info->wikidataItem !== null && $info->wikidataItem === $main?->wikidataItem => 'b',
					$main !== null && $info->wikidataItem !== null && $main->wikidataItem !== null
						&& (in_array($main->wikidataItem, $info->relatedItems, true) || in_array($info->wikidataItem, $main->relatedItems, true)) => 'c',
					default => null,
				};
				if ($rule !== null && !isset($variants[$earlier])) { // the main name is not itself a variant
					$variants[$name] = ['name' => $earlier, 'rule' => $rule];
					break;
				}
			}
		}
		return $variants;
	}


	/**
	 * „Silvie (jméno)“ → „Silvie“, „Jozef (prvé meno)“ → „Jozef“.
	 */
	public static function nameOfArticle(string $title): string
	{
		return (string) preg_replace('~ \((?:jméno|křestní jméno|meno|prvé meno)\)$~u', '', $title);
	}
}
