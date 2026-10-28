<?php

declare(strict_types=1);

namespace App\Model\Events;


/**
 * Reads events from the Parsoid HTML of a cs.wikipedia day page:
 * section "Události" → subsections "Česko" / "Svět" (possibly split further, e.g. by century).
 *
 * Every list item must be understood, otherwise the whole page is rejected:
 * - "1918 – text"                 → one event
 * - "1918" + nested items         → one event per nested item, all in 1918
 * - "1992 text:" + nested items   → one event, nested items appended to the text
 * - empty item                    → ignored (carries no content)
 * Years before Christ: "45 př. n. l." or "360 před naším letopočtem".
 * Births and deaths are separate h2 sections and are never read.
 * The only other exceptions are the explicit DayPageRules (ignored subsections by name,
 * known items without a year by day and text fingerprint).
 */
final class DayPageParser
{
	private const YearPattern = '~^(\d{1,4})(?:[\s\x{00A0}]*(př\.[\s\x{00A0}]*n\.[\s\x{00A0}]*l\.|před[\s\x{00A0}]+naším[\s\x{00A0}]+letopočtem))?(?:[\s\x{00A0}]*[–—-][\s\x{00A0}]*|[\s\x{00A0}]+|$)(.*)$~su';


	public function __construct(
		private readonly LinkFilter $linkFilter,
		private readonly DayPageRules $rules,
	) {
	}


	/**
	 * @throws DayPageParseException
	 */
	public function parse(string $html, MonthDay $day): DayPageParseResult
	{
		$events = $this->findEventsSection($this->load($html));
		$parsed = $skipped = [];
		$positions = [];

		foreach ($this->childElements($events) as $child) {
			if ($child->nodeName === 'h2') {
				continue;
			}
			$heading = $child->nodeName === 'section' ? $this->headingText($child) : null;
			$section = $heading === null ? null : Section::fromHeading($heading);
			$items = $this->topLevelItems($child);

			if ($section === null && $heading !== null && $this->rules->isIgnoredSubsection($heading)) {
				if ($items !== []) {
					$skipped[] = sprintf('ignorovaná podsekce „%s“ (%d položek)', $heading, count($items));
				}
				continue;
			}
			if ($section === null) {
				if ($items !== []) {
					$where = $heading === null ? 'mimo podsekci' : "v neznámé podsekci „{$heading}“";
					throw new DayPageParseException("Položka $where: " . $this->excerpt($items[0]));
				}
				continue;
			}

			foreach ($items as $item) {
				foreach ($this->parseItem($item, $section, $day, $skipped) as [$year, $text, $links]) {
					$positions[$section->value] = ($positions[$section->value] ?? 0) + 1;
					$parsed[] = new ParsedEvent($section, $year, $text, $positions[$section->value], $links);
				}
			}
		}

		return new DayPageParseResult($parsed, $skipped);
	}


	/**
	 * @param list<string> $skipped
	 * @return list<array{int, string, list<string>}> [year, text, link titles]
	 * @throws DayPageParseException
	 */
	private function parseItem(\DOMElement $item, Section $section, MonthDay $day, array &$skipped): array
	{
		$ownText = $this->text($item);
		$children = $this->nestedItems($item);
		if ($ownText === '' && $children === []) {
			return [];
		}

		if (!preg_match(self::YearPattern, $ownText, $m)) {
			$text = $this->text($item, includeNested: true);
			if ($this->rules->isKnownItemWithoutYear($day, $text)) {
				$skipped[] = "známá položka bez roku ({$section->value}): " . mb_substr($text, 0, 80);
				return [];
			}
			throw new DayPageParseException(sprintf(
				'Položka bez roku (%s, otisk %s): %s',
				$section->value,
				EventText::hash($text),
				$this->excerpt($item),
			));
		}
		try {
			$year = Year::fromText((int) $m[1], $m[2] !== '');
		} catch (\InvalidArgumentException $e) {
			throw new DayPageParseException("{$e->getMessage()}: " . $this->excerpt($item), 0, $e);
		}
		$rest = trim($m[3]);

		if ($children === []) {
			if ($rest === '') {
				throw new DayPageParseException("Rok bez textu ({$section->value}): " . $this->excerpt($item));
			}
			return [[$year, $rest, $this->links($item)]];
		}

		$childEvents = [];
		foreach ($children as $child) {
			$childText = $this->text($child, includeNested: true);
			if ($childText !== '') {
				$childEvents[] = [$childText, $this->links($child, includeNested: true)];
			}
		}

		if ($rest === '') {
			if ($childEvents === []) {
				throw new DayPageParseException("Rok bez textu ({$section->value}): " . $this->excerpt($item));
			}
			return array_map(fn(array $child): array => [$year, $child[0], $child[1]], $childEvents);
		}

		$childTexts = array_column($childEvents, 0);
		$last = array_pop($childTexts);
		$childTexts = array_map(fn(string $text): string => rtrim($text, ' .;'), $childTexts);
		$text = $rest . ' ' . implode('; ', [...$childTexts, $last]);
		$links = $this->links($item);
		foreach ($childEvents as [, $childLinks]) {
			$links = array_merge($links, $childLinks);
		}
		return [[$year, $text, array_values(array_unique($links))]];
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
	 * @throws DayPageParseException
	 */
	private function findEventsSection(\DOMDocument $dom): \DOMElement
	{
		foreach ($dom->getElementsByTagName('h2') as $h2) {
			if ($this->normalize($h2->textContent) === 'Události' && $h2->parentNode instanceof \DOMElement) {
				return $h2->parentNode;
			}
		}
		throw new DayPageParseException('Stránka nemá sekci „Události“');
	}


	private function headingText(\DOMElement $section): ?string
	{
		foreach ($this->childElements($section) as $child) {
			if (preg_match('~^h[3-6]$~', $child->nodeName)) {
				return $this->normalize($child->textContent);
			}
		}
		return null;
	}


	/**
	 * List items of all lists inside $container that are not nested in another item.
	 * @return list<\DOMElement>
	 */
	private function topLevelItems(\DOMElement $container): array
	{
		$items = [];
		$lists = $container->nodeName === 'ul' || $container->nodeName === 'ol'
			? [$container]
			: $this->xpath($container, './/*[self::ul or self::ol][not(ancestor::li)]');
		foreach ($lists as $list) {
			foreach ($this->childElements($list) as $item) {
				if ($item->nodeName === 'li') {
					$items[] = $item;
				}
			}
		}
		return $items;
	}


	/**
	 * @return list<\DOMElement>
	 */
	private function nestedItems(\DOMElement $item): array
	{
		$items = [];
		foreach ($this->childElements($item) as $child) {
			if ($child->nodeName === 'ul' || $child->nodeName === 'ol') {
				array_push($items, ...$this->topLevelItems($child));
			}
		}
		return $items;
	}


	/**
	 * Visible text of the item without references, hidden padding ({{0}}) and, unless asked, nested lists.
	 */
	private function text(\DOMNode $node, bool $includeNested = false): string
	{
		$text = '';
		foreach ($node->childNodes as $child) {
			if ($child instanceof \DOMText) {
				$text .= $child->data;
			} elseif ($child instanceof \DOMElement && !$this->isSkipped($child, $includeNested)) {
				$text .= $this->text($child, $includeNested);
			}
		}
		return $this->normalize($text);
	}


	/**
	 * Titles of existing linked pages that can be an event's article, in text order.
	 * @return list<string>
	 */
	private function links(\DOMElement $item, bool $includeNested = false): array
	{
		$titles = [];
		foreach ($this->xpath($item, './/a[@rel="mw:WikiLink"][@title]') as $link) {
			if ($this->isInsideSkipped($link, $item, $includeNested) || $this->hasClass($link, 'new')) {
				continue; // "new" = red link to a page that does not exist
			}
			$title = $this->normalize(explode('#', $link->getAttribute('title'))[0]);
			if ($title !== '' && $this->linkFilter->isArticleCandidate($title)) {
				$titles[] = $title;
			}
		}
		return array_values(array_unique($titles));
	}


	private function isSkipped(\DOMElement $element, bool $includeNested): bool
	{
		$style = str_replace(' ', '', $element->getAttribute('style'));
		return in_array($element->nodeName, ['style', 'script', 'link', 'meta'], true)
			|| (!$includeNested && in_array($element->nodeName, ['ul', 'ol'], true))
			|| ($element->nodeName === 'sup' && $this->hasClass($element, 'mw-ref'))
			|| str_contains($style, 'visibility:hidden')
			|| str_contains($style, 'display:none');
	}


	private function isInsideSkipped(\DOMElement $element, \DOMElement $root, bool $includeNested): bool
	{
		for ($node = $element; $node !== null && $node !== $root; $node = $node->parentNode) {
			if ($node instanceof \DOMElement && $this->isSkipped($node, $includeNested)) {
				return true;
			}
		}
		return false;
	}


	private function hasClass(\DOMElement $element, string $class): bool
	{
		return in_array($class, preg_split('~\s+~', $element->getAttribute('class')) ?: [], true);
	}


	/**
	 * @return list<\DOMElement>
	 */
	private function childElements(\DOMNode $node): array
	{
		$elements = [];
		foreach ($node->childNodes as $child) {
			if ($child instanceof \DOMElement) {
				$elements[] = $child;
			}
		}
		return $elements;
	}


	/**
	 * @return list<\DOMElement>
	 */
	private function xpath(\DOMElement $context, string $expression): array
	{
		assert($context->ownerDocument !== null);
		$elements = [];
		foreach ((new \DOMXPath($context->ownerDocument))->query($expression, $context) ?: [] as $node) {
			if ($node instanceof \DOMElement) {
				$elements[] = $node;
			}
		}
		return $elements;
	}


	/**
	 * Collapses ordinary whitespace; non-breaking spaces (Czech typography) are kept.
	 */
	private function normalize(string $text): string
	{
		return trim((string) preg_replace('~[ \t\r\n]+~', ' ', $text));
	}


	private function excerpt(\DOMElement $item): string
	{
		return mb_substr($this->normalize($item->textContent), 0, 120);
	}
}
