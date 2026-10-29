<?php

declare(strict_types=1);

namespace App\Model\NameDays;

use App\Model\Events\MonthDay;


/**
 * Explicit exceptions for the "Svátky" sections (config/name-day-rules.neon) – narrow on purpose.
 */
final class NameDayRules
{
	/**
	 * @param list<string> $czechLabels headings of the Czech list
	 * @param list<array{day: string, text: string, reason: string}> $ignoredItems items that look like names but are not name days
	 */
	public function __construct(
		private readonly array $czechLabels,
		private readonly array $ignoredItems,
	) {
	}


	public function isCzechLabel(string $label): bool
	{
		return in_array($label, $this->czechLabels, true);
	}


	public function ignoredReason(MonthDay $day, string $text): ?string
	{
		foreach ($this->ignoredItems as $item) {
			if ($item['day'] === $day->format() && $item['text'] === $text) {
				return $item['reason'];
			}
		}
		return null;
	}
}
