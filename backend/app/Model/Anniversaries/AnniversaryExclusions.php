<?php

declare(strict_types=1);

namespace App\Model\Anniversaries;

use App\Model\Events\MonthDay;
use App\Model\Selection\CardData;
use Nette\Schema\Expect;
use Nette\Schema\Processor;
use Nette\Schema\ValidationException;


/**
 * Round anniversaries excluded after a human review (config/anniversary-exclusions.neon).
 * Only removes – an excluded event can still be a normal card, just not a round anniversary.
 */
final class AnniversaryExclusions
{
	/** @var list<array{day: MonthDay, year: int, text: string, reason: string}> */
	private array $exclusions = [];


	/**
	 * @param array<mixed> $config
	 */
	public function __construct(array $config)
	{
		$schema = Expect::listOf(Expect::structure([
			'day' => Expect::string()->pattern('\d{2}-\d{2}')->required(),
			'year' => Expect::int()->required(),
			'text' => Expect::string()->min(8)->required(),
			'reason' => Expect::string()->min(3)->required(),
		]));
		try {
			/** @var list<\stdClass> $items */
			$items = (new Processor)->process($schema, $config);
		} catch (ValidationException $e) {
			throw new \InvalidArgumentException('config/anniversary-exclusions.neon: ' . $e->getMessage(), 0, $e);
		}
		foreach ($items as $item) {
			$this->exclusions[] = ['day' => MonthDay::fromString($item->day), 'year' => $item->year, 'text' => $item->text, 'reason' => $item->reason];
		}
	}


	public function isExcluded(CardData $event): bool
	{
		foreach ($this->exclusions as $exclusion) {
			if ($exclusion['day']->equals($event->day) && $exclusion['year'] === $event->year && str_contains($event->text, $exclusion['text'])) {
				return true;
			}
		}
		return false;
	}


	/**
	 * @return list<array{day: MonthDay, year: int, text: string, reason: string}>
	 */
	public function all(): array
	{
		return $this->exclusions;
	}
}
