<?php

declare(strict_types=1);

namespace App\Api\Dto;

use App\Model\Events\MonthDay;
use App\Model\Selection\Card;


final readonly class DayResponse implements \JsonSerializable
{
	/**
	 * @param list<Card> $cards
	 */
	public function __construct(
		private MonthDay $day,
		private array $cards,
	) {
	}


	/**
	 * @return array{date: string, cards: list<CardResponse>}
	 */
	public function jsonSerialize(): array
	{
		return [
			'date' => $this->day->format(),
			'cards' => array_map(fn(Card $card): CardResponse => new CardResponse($card), $this->cards),
		];
	}
}
