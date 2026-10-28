<?php

declare(strict_types=1);

namespace App\Core\Database;

use Nette\Database\Connection;
use Nette\Database\DriverException;
use Nextras\Migrations\Bridges\NetteDatabase\NetteAdapter;
use Nextras\Migrations\Drivers\MySqlDriver;
use Nextras\Migrations\Engine\Finder;
use Nextras\Migrations\Engine\Runner;
use Nextras\Migrations\Entities\Group;
use Nextras\Migrations\Extensions\SqlHandler;
use Nextras\Migrations\IPrinter;


/**
 * Schema changes only through SQL migration files (Nextras Migrations, group "structures").
 */
final class Migrations
{
	private const Group = 'structures';
	private const Extension = 'sql';

	private readonly MySqlDriver $driver;


	public function __construct(
		Connection $connection,
		private readonly string $directory,
	) {
		$this->driver = new MySqlDriver(new NetteAdapter($connection));
	}


	/**
	 * Runs all pending migrations; the printer rethrows errors, so a failure is never silent.
	 */
	public function migrate(IPrinter $printer): void
	{
		$runner = new Runner($this->driver, $printer);
		$runner->addGroup($this->createGroup());
		$runner->addExtensionHandler(self::Extension, new SqlHandler($this->driver));
		$runner->run(Runner::MODE_CONTINUE);
	}


	/**
	 * Names of migration files not yet completed in the database. Works on an empty database
	 * (no migrations table yet): then every file is pending.
	 * @return list<string>
	 */
	public function pending(): array
	{
		$files = (new Finder)->find([$this->createGroup()], [self::Extension]);

		try {
			$completed = [];
			foreach ($this->driver->getAllMigrations() as $migration) {
				if ($migration->completed && $migration->group === self::Group) {
					$completed[$migration->filename] = true;
				}
			}
		} catch (DriverException) {
			$completed = []; // migrations table does not exist yet
		}

		$pending = [];
		foreach ($files as $file) {
			if (!isset($completed[$file->name])) {
				$pending[] = $file->name;
			}
		}
		return $pending;
	}


	private function createGroup(): Group
	{
		$group = new Group;
		$group->name = self::Group;
		$group->enabled = true;
		$group->directory = $this->directory;
		$group->dependencies = [];
		return $group;
	}
}
