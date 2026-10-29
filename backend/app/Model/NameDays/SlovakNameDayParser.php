<?php

declare(strict_types=1);

namespace App\Model\NameDays;


/**
 * Reads the Slovak name days from the introduction of a sk.wikipedia day page (Parsoid HTML): the
 * page template ends the first paragraph with „Meniny má Jozef.“ / „Meniny má Andrea a Artur.“ /
 * „Meniny má Alexandra, Sandra a Karina.“ No such sentence and no mention of „meniny“ means the day
 * has none (1. 1., 25. 12.); a mention in any other form fails the day – nothing is guessed.
 */
final class SlovakNameDayParser
{
	private const Name = '\p{Lu}[\p{Ll}\'’]+(?:-\p{Lu}[\p{Ll}]+)?';


	/**
	 * @return list<ParsedNameDay>
	 * @throws NameDayParseException
	 */
	public function parse(string $html): array
	{
		$lead = $this->leadText($html);
		$sentences = preg_match_all(sprintf('~Meniny má (%1$s(?:, %1$s)*(?: a %1$s)?)\.~u', self::Name), $lead, $m);
		if ($sentences === 0) {
			if (preg_match('~menin~iu', $lead)) {
				throw new NameDayParseException('Nečitelná zmínka o meninách v úvodu stránky: „' . $this->excerpt($lead, 'menin') . '“');
			}
			return [];
		}
		if ($sentences > 1 || preg_match_all('~menin~iu', $lead) > 1) {
			throw new NameDayParseException('Úvod stránky zmiňuje meniny víckrát');
		}

		preg_match_all('~' . self::Name . '~u', $m[1][0], $names);
		return array_map(
			fn(string $name, int $index): ParsedNameDay => new ParsedNameDay(Country::Slovak, $name, 1, $index + 1, null),
			$names[0],
			array_keys($names[0]),
		);
	}


	/**
	 * Text of the page before the first h2 (the introduction), with whitespace normalized.
	 * @throws NameDayParseException
	 */
	private function leadText(string $html): string
	{
		$dom = new \DOMDocument;
		$previous = libxml_use_internal_errors(true); // libxml does not know HTML5 elements like <section>
		$dom->loadHTML('<?xml encoding="utf-8"?>' . $html, LIBXML_NONET | LIBXML_HTML_NODEFDTD);
		libxml_clear_errors();
		libxml_use_internal_errors($previous);

		$paragraphs = (new \DOMXPath($dom))->query('//body//p[not(preceding::h2)][not(ancestor::table)]');
		if ($paragraphs === false || $paragraphs->length === 0) {
			throw new NameDayParseException('Stránka nemá úvodní odstavec');
		}
		$text = '';
		foreach ($paragraphs as $paragraph) {
			$text .= $paragraph instanceof \DOMElement ? ' ' . $paragraph->textContent : '';
		}
		return trim((string) preg_replace('~[\s\x{00A0}]+~u', ' ', $text));
	}


	private function excerpt(string $text, string $needle): string
	{
		$position = (int) mb_stripos($text, $needle);
		return mb_substr($text, max(0, $position - 20), 100);
	}
}
