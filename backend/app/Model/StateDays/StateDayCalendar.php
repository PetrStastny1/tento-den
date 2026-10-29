<?php

declare(strict_types=1);

namespace App\Model\StateDays;

use App\Model\Events\MonthDay;
use App\Model\Selection\CardData;
use Nette\Schema\Expect;
use Nette\Schema\Processor;
use Nette\Schema\ValidationException;


/**
 * All days of Act No. 245/2000 Coll. from config/state-days.neon, validated when the container is built.
 */
final class StateDayCalendar
{
	/** @var list<StateDay> */
	private array $days = [];


	/**
	 * @param array<mixed> $config the stateDays parameter
	 * @throws \InvalidArgumentException when the configuration is invalid
	 */
	public function __construct(array $config)
	{
		$schema = Expect::listOf(Expect::structure([
			'date' => Expect::string()->pattern('\d{2}-\d{2}'),
			'easter' => Expect::int(),
			'name' => Expect::string()->required(),
			'type' => Expect::anyOf(...array_column(StateDayType::cases(), 'value'))->required(),
			'source' => Expect::string()->pattern('https://www\.zakonyprolidi\.cz/cs/2000-245#p[124]')->required(),
			'event' => Expect::anyOf(Expect::structure([
				'year' => Expect::int()->required(),
				'text' => Expect::string()->min(8)->required(),
			]), null)->default(null),
			'article' => Expect::string(),
		]));
		try {
			/** @var list<\stdClass> $items */
			$items = (new Processor)->process($schema, $config);
		} catch (ValidationException $e) {
			throw new \InvalidArgumentException('config/state-days.neon: ' . $e->getMessage(), 0, $e);
		}

		foreach ($items as $item) {
			$this->days[] = new StateDay(
				name: $item->name,
				type: StateDayType::from($item->type),
				source: $item->source,
				date: $item->date === null ? null : MonthDay::fromString($item->date),
				easterOffset: $item->easter,
				eventYear: $item->event?->year,
				eventText: $item->event?->text,
				article: $item->article,
			);
		}
		$ids = array_map(fn(StateDay $day): string => $day->id, $this->days);
		if (count($ids) !== count(array_unique($ids))) {
			throw new \InvalidArgumentException('config/state-days.neon: duplicate names');
		}
	}


	/**
	 * @return list<StateDay> in the order of the act
	 */
	public function all(): array
	{
		return $this->days;
	}


	/**
	 * @return list<StateDay>
	 */
	public function forDay(MonthDay $day, ?int $year): array
	{
		return array_values(array_filter($this->days, fn(StateDay $stateDay): bool => $stateDay->occursOn($day, $year)));
	}


	/**
	 * Active events of the day the state day commemorates: its year and a text containing its fragment.
	 * Only exactly one match counts – anything else is reported, never guessed.
	 * @param array<int, CardData> $events
	 * @return list<int> event ids
	 */
	public function matchingEvents(StateDay $stateDay, array $events): array
	{
		if (!$stateDay->hasEvent()) {
			return [];
		}
		$ids = [];
		foreach ($events as $id => $event) {
			if ($event->year === $stateDay->eventYear && str_contains($event->text, (string) $stateDay->eventText)) {
				$ids[] = $id;
			}
		}
		return $ids;
	}
}
