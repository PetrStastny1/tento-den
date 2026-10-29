<?php

declare(strict_types=1);

namespace App\Model\Wikipedia;

use App\Core\Clock\Clock;
use App\Core\Http\HttpClient;
use App\Core\Http\HttpException;
use App\Model\Import\ArticleSummary;
use App\Model\Import\ArticleSummarySource;
use App\Model\Import\SourceException;


/**
 * The introduction of an article: the first paragraph of its lead (Parsoid HTML of the current
 * revision), word for word – only references, styles and hidden elements are left out. Not REST
 * page/summary: that one drops text in parentheses („clara („jasná“, „svetlá“…)“ became „clara“).
 * The revision comes from Content-Revision-Id; a redirect is not followed (titles come resolved).
 */
final class WikipediaLeadSource implements ArticleSummarySource
{
	public function __construct(
		private readonly HttpClient $http,
		private readonly Clock $clock,
		private readonly string $language = 'cs',
	) {
	}


	public function fetch(string $title): ?ArticleSummary
	{
		$url = "https://{$this->language}.wikipedia.org/api/rest_v1/page/html/" . rawurlencode(str_replace(' ', '_', $title)) . '?redirect=false';
		try {
			$response = $this->http->get($url, ['Accept' => 'text/html']);
		} catch (HttpException $e) {
			throw new SourceException($e->getMessage(), 0, $e);
		}
		if ($response->status === 404) {
			return null;
		}
		if ($response->status !== 200) {
			throw new SourceException("Article „{$title}“: HTTP {$response->status}");
		}
		$revision = $response->header('Content-Revision-Id');
		if ($revision === null || !ctype_digit($revision)) {
			throw new SourceException("Article „{$title}“: no Content-Revision-Id");
		}
		$extract = self::lead($response->body);
		if ($extract === null) {
			throw new SourceException("Article „{$title}“ (revision $revision): no introductory paragraph");
		}
		return new ArticleSummary($title, $extract, (int) $revision, $this->clock->now());
	}


	/**
	 * The first non-empty paragraph before the first heading, outside tables (infoboxes).
	 */
	public static function lead(string $html): ?string
	{
		$dom = new \DOMDocument;
		$previous = libxml_use_internal_errors(true); // libxml does not know HTML5 elements like <section>
		$dom->loadHTML('<?xml encoding="utf-8"?>' . $html, LIBXML_NONET | LIBXML_HTML_NODEFDTD);
		libxml_clear_errors();
		libxml_use_internal_errors($previous);

		$xpath = new \DOMXPath($dom);
		$paragraphs = $xpath->query('//p[not(preceding::h2)][not(ancestor::table)][not(ancestor::*[contains(@class, "infobox")])]');
		foreach ($paragraphs === false ? [] : $paragraphs as $paragraph) {
			if (!$paragraph instanceof \DOMElement) {
				continue;
			}
			$hidden = $xpath->query('.//sup | .//style | .//script | .//*[contains(@class, "mw-ref") or contains(@class, "reference") or contains(@class, "noprint") or contains(@class, "mw-empty-elt")] | .//*[contains(@style, "display:none")]', $paragraph);
			foreach ($hidden === false ? [] : iterator_to_array($hidden) as $node) {
				if ($node instanceof \DOMElement) {
					$node->parentNode?->removeChild($node);
				}
			}
			$text = trim((string) preg_replace('~[\s\x{00A0}]+~u', ' ', $paragraph->textContent));
			$text = (string) preg_replace('~\s+([.,;:!?)])~u', '$1', $text); // a removed reference leaves a space before punctuation
			if ($text !== '') {
				return $text;
			}
		}
		return null;
	}
}
