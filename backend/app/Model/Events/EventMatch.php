<?php

declare(strict_types=1);

namespace App\Model\Events;


final readonly class EventMatch
{
	public function __construct(
		public StoredEvent $stored,
		public IncomingEvent $incoming,
		public bool $exact,
		public float $similarity,
	) {
	}
}
