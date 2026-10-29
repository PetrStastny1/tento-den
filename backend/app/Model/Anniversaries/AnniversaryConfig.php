<?php

declare(strict_types=1);

namespace App\Model\Anniversaries;


/**
 * config/anniversaries.neon: which Wikidata classes make an article a big event and the minimum
 * of language editions.
 */
final readonly class AnniversaryConfig
{
	/**
	 * @param array<string, string> $eventClasses Q-id => Czech label
	 */
	public function __construct(
		public array $eventClasses,
		public int $minSitelinks,
	) {
		foreach (array_keys($eventClasses) as $class) {
			if (!preg_match('~^Q\d+$~', (string) $class)) {
				throw new \InvalidArgumentException("config/anniversaries.neon: invalid class '$class'");
			}
		}
		if ($minSitelinks < 1) {
			throw new \InvalidArgumentException('config/anniversaries.neon: minSitelinks must be positive');
		}
	}
}
