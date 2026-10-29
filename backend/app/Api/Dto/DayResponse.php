<?php

declare(strict_types=1);

namespace App\Api\Dto;

use App\Model\Events\MonthDay;
use App\Model\Selection\Card;
use App\Model\Selection\DayContent;
use App\Model\Selection\HolidayCard;


final readonly class DayResponse implements \JsonSerializable
{
	public function __construct(
		private MonthDay $day,
		private DayContent $content,
	) {
	}


	/**
	 * @return array{date: string, holidays: list<HolidayResponse>, cards: list<CardResponse>}
	 */
	public function jsonSerialize(): array
	{
		return [
			'date' => $this->day->format(),
			'holidays' => array_map(fn(HolidayCard $holiday): HolidayResponse => new HolidayResponse($holiday, $this->day), $this->content->holidays),
			'cards' => array_map(fn(Card $card): CardResponse => new CardResponse($card), $this->content->cards),
		];
	}
}
