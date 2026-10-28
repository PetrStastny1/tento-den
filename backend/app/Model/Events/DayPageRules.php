<?php

declare(strict_types=1);

namespace App\Model\Events;


/**
 * Explicit, narrow exceptions for day pages (config/day-page-rules.neon). There is no general
 * skipping: an unknown subsection or an unknown item without a year still fails the whole day,
 * and a known item fails again as soon as its text changes (its fingerprint no longer matches).
 * A subsection can also be mapped to a section, but only on the listed day.
 */
final readonly class DayPageRules
{
	/** @var array<string, true> */
	private array $ignoredSubsections;

	/** @var array<string, true> "MM-DD|fingerprint" */
	private array $knownItems;

	/** @var array<string, Section> "MM-DD|heading" => section */
	private array $mappedSubsections;


	/**
	 * @param list<string> $ignoredSubsections exact headings under "Události"
	 * @param list<array{day: string, fingerprint: string, text?: string}> $knownItemsWithoutYear
	 *        fingerprint = EventText::hash() of the item text; text is only for humans
	 * @param list<array{day: string, heading: string, section: string}> $mappedSubsections
	 */
	public function __construct(array $ignoredSubsections, array $knownItemsWithoutYear, array $mappedSubsections = [])
	{
		$mapped = [];
		foreach ($mappedSubsections as $rule) {
			$mapped[MonthDay::fromString($rule['day'])->format() . '|' . $rule['heading']] = Section::from($rule['section']);
		}
		$this->mappedSubsections = $mapped;
		$this->ignoredSubsections = array_fill_keys($ignoredSubsections, true);
		$known = [];
		foreach ($knownItemsWithoutYear as $item) {
			if (!preg_match('~^[0-9a-f]{64}$~', $item['fingerprint'])) {
				throw new \InvalidArgumentException("Invalid fingerprint for {$item['day']}: {$item['fingerprint']}");
			}
			$known[MonthDay::fromString($item['day'])->format() . '|' . $item['fingerprint']] = true;
		}
		$this->knownItems = $known;
	}


	public function isIgnoredSubsection(string $heading): bool
	{
		return isset($this->ignoredSubsections[$heading]);
	}


	public function mappedSection(MonthDay $day, string $heading): ?Section
	{
		return $this->mappedSubsections[$day->format() . '|' . $heading] ?? null;
	}


	public function isKnownItemWithoutYear(MonthDay $day, string $text): bool
	{
		return isset($this->knownItems[$day->format() . '|' . EventText::hash($text)]);
	}
}
