<?php

declare(strict_types=1);

namespace App\Api\Dto;

use App\Model\Selection\Card;


/**
 * A single card plus its status – favorites may point to an event that is no longer on the page.
 */
final readonly class EventResponse implements \JsonSerializable
{
	public function __construct(
		private Card $card,
	) {
	}


	/**
	 * @return array<string, mixed>
	 */
	public function jsonSerialize(): array
	{
		return (new CardResponse($this->card))->jsonSerialize() + ['status' => $this->card->data->status->value];
	}
}
