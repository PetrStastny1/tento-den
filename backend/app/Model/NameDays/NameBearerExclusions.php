<?php

declare(strict_types=1);

namespace App\Model\NameDays;

use Nette\Schema\Expect;
use Nette\Schema\Processor;
use Nette\Schema\ValidationException;


/**
 * Bearers excluded after a human review (config/name-bearer-exclusions.neon) – only removals.
 */
final class NameBearerExclusions
{
	/** @var array<string, list<string>> "cz:Jan" => Wikidata items */
	private array $excluded = [];


	/**
	 * @param array<mixed> $config
	 */
	public function __construct(array $config)
	{
		$schema = Expect::listOf(Expect::structure([
			'country' => Expect::anyOf(...array_column(Country::cases(), 'value'))->required(),
			'name' => Expect::string()->required(),
			'bearer' => Expect::string()->pattern('Q\d+')->required(),
			'reason' => Expect::string()->min(3)->required(),
		]));
		try {
			/** @var list<\stdClass> $items */
			$items = (new Processor)->process($schema, $config);
		} catch (ValidationException $e) {
			throw new \InvalidArgumentException('config/name-bearer-exclusions.neon: ' . $e->getMessage(), 0, $e);
		}
		foreach ($items as $item) {
			$this->excluded["{$item->country}:{$item->name}"][] = $item->bearer;
		}
	}


	/**
	 * @return list<string> Wikidata items of people not to show for this name in this mode
	 */
	public function forName(Country $country, string $name): array
	{
		return $this->excluded["{$country->value}:{$name}"] ?? [];
	}
}
