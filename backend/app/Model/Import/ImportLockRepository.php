<?php

declare(strict_types=1);

namespace App\Model\Import;

use App\Model\Events\MonthDay;
use Nette\Database\Explorer;


/**
 * Per-day advisory lock (MariaDB GET_LOCK) so two processes never import the same day at once.
 * A transaction alone would not prevent two concurrent imports from inserting the same new event.
 * The lock belongs to the connection and is released automatically if the process dies.
 */
final class ImportLockRepository
{
	public function __construct(
		private readonly Explorer $database,
	) {
	}


	public function acquire(MonthDay $day): bool
	{
		return (int) $this->database->fetchField('SELECT GET_LOCK(?, 0)', $this->name($day)) === 1;
	}


	public function release(MonthDay $day): void
	{
		$this->database->query('DO RELEASE_LOCK(?)', $this->name($day));
	}


	private function name(MonthDay $day): string
	{
		return 'tento_den.import.' . $day->format();
	}
}
