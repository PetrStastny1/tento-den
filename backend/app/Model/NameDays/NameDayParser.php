<?php

declare(strict_types=1);

namespace App\Model\NameDays;

use App\Model\Events\MonthDay;


/**
 * Reads the name days of Česko and Slovensko from the "Svátky" section of a cs.wikipedia day page
 * (Parsoid HTML, the same revision as the events).
 *
 * The section is free-form: the Czech list (heading „Česko“ and its variants as h3, dt or a paragraph)
 * mixes names with holidays and notes, Slovak names are usually a line „Slovensko: Jozef“ under
 * „Svět“. A list item is a name day only when it is a list of names: capitalised words separated by
 * „,“ „/“ „a“ „též“ (a variant may follow in parentheses: „Arsen (Arzen)“), optionally ending with
 * a note in parentheses or after a dash. An item that starts like a name list
 * but does not read as one fails the whole day – nothing is guessed. Other items are not name days
 * and are skipped (reported in the log).
 */
final class NameDayParser
{
	private const SlovakLabel = 'Slovensko';
	private const Name = '\p{Lu}[\p{Ll}\'’]+(?:-\p{Lu}[\p{Ll}]+)?';
	private const Separator = '(?:\s*,\s*(?:též\s+)?|\s*/\s*|\s+(?:a|též)\s+)';


	public function __construct(
		private readonly NameDayRules $rules,
	) {
	}


	/**
	 * @throws NameDayParseException
	 */
	public function parse(string $html, MonthDay $day): NameDayParseResult
	{
		$dom = $this->load($html);
		$section = $this->findSection($dom);
		if ($section === null) {
			throw new NameDayParseException('Stránka nemá sekci „Svátky“');
		}

		$blocks = $this->blocks($dom, $section);
		$czech = array_values(array_filter($blocks, fn(array $block): bool => $this->rules->isCzechLabel($block['label'])));
		if (count($czech) > 1) {
			throw new NameDayParseException(sprintf('Sekce „Svátky“ má %d seznamy pro Česko', count($czech)));
		}
		$hasItems = array_filter($blocks, fn(array $block): bool => $block['items'] !== []) !== [];
		if ($czech === [] && $hasItems) {
			throw new NameDayParseException('Sekce „Svátky“ nemá seznam pro Česko (nadpisy: ' . implode(', ', array_column($blocks, 'label')) . ')');
		}

		$result = $skipped = [];
		$lines = [Country::Czech->value => 0, Country::Slovak->value => 0];
		foreach ($blocks as $block) {
			$blockCountry = match (true) {
				$this->rules->isCzechLabel($block['label']) => Country::Czech,
				$block['label'] === self::SlovakLabel => Country::Slovak,
				default => null,
			};
			foreach ($block['items'] as $text) {
				$country = $blockCountry;
				// „Slovensko: Jozef“ / „Slovensko – Daniela“ anywhere in the section
				if (preg_match('~^' . self::SlovakLabel . '\s*[:–-]\s*(.+)$~u', $text, $m)) {
					[$country, $text] = [Country::Slovak, $m[1]];
				}
				if ($country === null) {
					continue; // other countries, liturgical calendars…
				}

				$reason = $this->rules->ignoredReason($day, $text);
				if ($reason !== null) {
					$skipped[] = "{$country->value}: „{$text}“ – pravidlo: {$reason}";
					continue;
				}
				$names = $this->names($text);
				if ($names === null) {
					if (preg_match('~^' . self::Name . '(?:\s*[,;/(]|\s+(?:a|též)\s|$)~u', $text)) { // not „Sv. …“ or „Svátek: …“
						throw new NameDayParseException("Nečitelný seznam jmen ({$country->value}): „{$text}“");
					}
					$skipped[] = "{$country->value}: „{$text}“";
					continue;
				}

				$line = ++$lines[$country->value];
				foreach ($names['names'] as $position => $name) {
					$result[] = new ParsedNameDay($country, $name, $line, $position + 1, $names['note']);
				}
			}
		}
		return new NameDayParseResult($this->withoutDuplicates($result), $skipped);
	}


	/**
	 * @return array{names: list<string>, note: ?string}|null null when the text is not a list of names
	 */
	private function names(string $text): ?array
	{
		$name = sprintf('%1$s(?:\s*\(%1$s\))?', self::Name); // „Arsen (Arzen)“: a variant in parentheses
		$pattern = sprintf('~^(%1$s(?:%2$s%1$s)*)\s*,?\s*(?:\(([^()]+)\)|\s–\s(.+))?$~u', $name, self::Separator);
		if (!preg_match($pattern, $text, $m)) {
			return null;
		}
		preg_match_all('~' . self::Name . '~u', $m[1], $names);
		$note = trim(($m[2] ?? '') . ($m[3] ?? ''));
		return ['names' => $names[0], 'note' => $note === '' ? null : $note];
	}


	/**
	 * A name listed twice for one country (e.g. in the Czech list and again as „Slovensko:“) is kept once.
	 * @param list<ParsedNameDay> $nameDays
	 * @return list<ParsedNameDay>
	 */
	private function withoutDuplicates(array $nameDays): array
	{
		$seen = $unique = [];
		foreach ($nameDays as $nameDay) {
			$key = $nameDay->country->value . "\0" . $nameDay->name;
			if (!isset($seen[$key])) {
				$seen[$key] = true;
				$unique[] = $nameDay;
			}
		}
		return $unique;
	}


	/**
	 * The section split into labelled blocks in document order: a heading (h3, h4), a definition term
	 * or a paragraph starts a block, list items (not nested ones) are its items.
	 * @return list<array{label: string, items: list<string>}>
	 */
	private function blocks(\DOMDocument $dom, \DOMElement $section): array
	{
		$labels = [''];
		$items = [[]];
		$nodes = (new \DOMXPath($dom))->query(
			'.//*[self::h3 or self::h4 or self::dt or self::dd or self::p or self::li][not(ancestor::li)][not(ancestor::table)]',
			$section,
		);
		foreach ($nodes === false ? [] : $nodes as $node) {
			if (!$node instanceof \DOMElement) {
				continue;
			}
			$text = $this->text($node);
			if (in_array($node->nodeName, ['h3', 'h4', 'dt'], true)) {
				$labels[] = $this->label($text);
				$items[] = [];
			} elseif ($node->nodeName === 'p') {
				// „Česko:“ as a paragraph; text after the colon on the same line is an item
				[$label, $rest] = array_pad(explode(':', explode("\n", $text)[0], 2), 2, '');
				$labels[] = $this->label($label);
				$items[] = trim($rest) === '' ? [] : [$this->normalize($rest)];
			} elseif ($text !== '') {
				$items[count($items) - 1][] = $this->normalize($text);
			}
		}
		return array_map(fn(string $label, array $blockItems): array => ['label' => $label, 'items' => $blockItems], $labels, $items);
	}


	private function label(string $text): string
	{
		return trim($this->normalize($text), " :\t");
	}


	private function findSection(\DOMDocument $dom): ?\DOMElement
	{
		foreach ($dom->getElementsByTagName('h2') as $h2) {
			if ($this->normalize($h2->textContent) === 'Svátky' && $h2->parentNode instanceof \DOMElement) {
				return $h2->parentNode;
			}
		}
		return null;
	}


	private function load(string $html): \DOMDocument
	{
		$dom = new \DOMDocument;
		$previous = libxml_use_internal_errors(true); // libxml does not know HTML5 elements like <section>
		$dom->loadHTML('<?xml encoding="utf-8"?>' . $html, LIBXML_NONET | LIBXML_HTML_NODEFDTD);
		libxml_clear_errors();
		libxml_use_internal_errors($previous);
		return $dom;
	}


	/**
	 * Visible text without references, nested lists and styles; line breaks (<br>) are kept.
	 */
	private function text(\DOMNode $node): string
	{
		$text = '';
		foreach ($node->childNodes as $child) {
			if ($child instanceof \DOMText) {
				$text .= $child->data;
			} elseif ($child instanceof \DOMElement) {
				if ($child->nodeName === 'br') {
					$text .= "\n";
				} elseif (!in_array($child->nodeName, ['style', 'script', 'ul', 'ol', 'sup'], true)) {
					$text .= $this->text($child);
				}
			}
		}
		return trim((string) preg_replace('~[ \t\x{00A0}]+~u', ' ', $text));
	}


	private function normalize(string $text): string
	{
		return trim((string) preg_replace('~[\s\x{00A0}]+~u', ' ', $text));
	}
}
