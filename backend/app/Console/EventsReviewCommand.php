<?php

declare(strict_types=1);

namespace App\Console;

use App\Core\Console\Command;
use App\Core\Console\Output;
use App\Core\Console\UsageException;
use App\Model\Events\EventRepository;
use App\Model\Events\Year;


final class EventsReviewCommand implements Command
{
	public function __construct(
		private readonly EventRepository $events,
	) {
	}


	public function getName(): string
	{
		return 'events:review';
	}


	public function getDescription(): string
	{
		return 'Vypíše události, u kterých import nerozhodl, zda jde o úpravu jiné události';
	}


	public function run(array $options, Output $output): int
	{
		if ($options !== []) {
			throw new UsageException('Použití: events:review');
		}

		$events = $this->events->findForReview();
		foreach ($events as $event) {
			$output->writeln(sprintf(
				'#%d %02d-%02d %s %s: %s',
				$event->id,
				$event->month,
				$event->day,
				$event->section->value,
				Year::format($event->year),
				$event->text,
			));
			$output->writeln('    ' . $event->reviewNote);
		}
		$output->writeln(sprintf('Celkem k revizi: %d', count($events)));
		return self::ExitOk;
	}
}
