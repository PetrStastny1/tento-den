<?php

declare(strict_types=1);

namespace App\Model\Selection;


/**
 * What the selection needs to know about an event's text (config/selection.neon): whether it is
 * routine agenda, what kind of event it is, and whether it is the kind of event the page is about.
 */
final class EventClassifier
{
	public function __construct(
		private readonly TextRules $excluded,
		private readonly TextRules $exceptions,
		private readonly TextRules $articleExceptions,
		private readonly TextRules $types,
		private readonly TextRules $remarkable,
	) {
	}


	/**
	 * Name of the matching rule, or null. A turning point (a new state, a coup, a peace) is never excluded,
	 * nor is an event linking an article of its own kind (a treaty with its own article is not routine).
	 * @param list<string> $linkedTitles titles of the articles the event links
	 */
	public function excludedReason(string $text, array $linkedTitles = []): ?string
	{
		if ($this->exceptions->match($text) !== null) {
			return null;
		}
		$reason = $this->excluded->match($text);
		foreach ($linkedTitles as $title) {
			if ($reason !== null && $this->articleExceptions->matchesRule($reason, $title)) {
				return null;
			}
		}
		return $reason;
	}


	public function type(string $text): ?string
	{
		return $this->types->match($text);
	}


	public function isRemarkable(string $text): bool
	{
		return $this->remarkable->match($text) !== null;
	}
}
