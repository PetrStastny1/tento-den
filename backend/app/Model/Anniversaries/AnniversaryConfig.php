<?php

declare(strict_types=1);

namespace App\Model\Anniversaries;

use App\Model\Events\Section;


/**
 * config/anniversaries.neon: which Wikidata classes make an article a big event and the minimum
 * of language editions, by section.
 */
final readonly class AnniversaryConfig
{
	/**
	 * @param array<string, string> $eventClasses Q-id => Czech label
	 * @param array<string, int> $minSitelinks section (cz, world) => minimum
	 */
	public function __construct(
		public array $eventClasses,
		public array $minSitelinks,
	) {
		foreach (array_keys($eventClasses) as $class) {
			if (!preg_match('~^Q\d+$~', (string) $class)) {
				throw new \InvalidArgumentException("config/anniversaries.neon: invalid class '$class'");
			}
		}
		foreach (Section::cases() as $section) {
			if (($minSitelinks[$section->value] ?? 0) < 1) {
				throw new \InvalidArgumentException("config/anniversaries.neon: minSitelinks.{$section->value} must be positive");
			}
		}
	}


	public function minSitelinks(Section $section): int
	{
		return $this->minSitelinks[$section->value];
	}
}
