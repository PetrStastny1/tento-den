<?php

declare(strict_types=1);

namespace App\Api\Dto;

use App\Model\Events\MonthDay;
use App\Model\NameDays\CountryNameDays;
use App\Model\Selection\Card;
use App\Model\Selection\DayContent;
use App\Model\Selection\HolidayCard;


final readonly class DayResponse implements \JsonSerializable
{
	/**
	 * @param array{cz: ?CountryNameDays, sk: ?CountryNameDays} $nameDays
	 */
	public function __construct(
		private MonthDay $day,
		private DayContent $content,
		private array $nameDays,
	) {
	}


	/**
	 * @return array{date: string, anniversaryYear: ?int, nameDays: NameDaysResponse, holidays: list<HolidayResponse>, cards: list<CardResponse>}
	 */
	public function jsonSerialize(): array
	{
		return [
			'date' => $this->day->format(),
			'anniversaryYear' => $this->content->anniversaryYear,
			'nameDays' => new NameDaysResponse($this->nameDays),
			'holidays' => array_map(fn(HolidayCard $holiday): HolidayResponse => new HolidayResponse($holiday, $this->day), $this->content->holidays),
			'cards' => array_map(fn(Card $card): CardResponse => new CardResponse($card), $this->content->cards),
		];
	}
}
