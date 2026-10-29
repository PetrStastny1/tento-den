<?php

declare(strict_types=1);

namespace App\Model\Selection;


final readonly class Card
{
	public function __construct(
		public CardData $data,
		public bool $sensitive,
	) {
	}
}
