<?php

declare(strict_types=1);

namespace App\Core\Console;


/**
 * Minimal dispatcher: picks the command by name, parses "--name=value" / "--flag"
 * options and turns usage errors into exit code 2. Nothing more.
 */
final class ConsoleApplication
{
	/** @var array<string, Command> */
	private array $commands = [];


	/**
	 * @param list<Command> $commands
	 */
	public function __construct(array $commands)
	{
		foreach ($commands as $command) {
			$this->commands[$command->getName()] = $command;
		}
		ksort($this->commands);
	}


	/**
	 * @param list<string> $argv arguments without the script name
	 */
	public function run(array $argv, Output $output): int
	{
		$name = array_shift($argv);
		if ($name === null || !isset($this->commands[$name])) {
			if ($name !== null) {
				$output->error("Neznámý příkaz: $name");
			}
			$this->printCommands($output);
			return Command::ExitUsage;
		}

		try {
			return $this->commands[$name]->run($this->parseOptions($argv), $output);
		} catch (UsageException $e) {
			$output->error($e->getMessage());
			return Command::ExitUsage;
		}
	}


	/**
	 * @param list<string> $argv
	 * @return array<string, string|true>
	 */
	private function parseOptions(array $argv): array
	{
		$options = [];
		foreach ($argv as $argument) {
			if (!preg_match('~^--([a-z][a-z-]*)(?:=(.*))?$~', $argument, $m)) {
				throw new UsageException("Neplatný argument: $argument (očekávám --nazev nebo --nazev=hodnota)");
			}
			$options[$m[1]] = $m[2] ?? true;
		}
		return $options;
	}


	private function printCommands(Output $output): void
	{
		$output->writeln('Použití: bin/console.php <příkaz> [--volba=hodnota]');
		$output->writeln('Příkazy:');
		foreach ($this->commands as $name => $command) {
			$output->writeln(sprintf('  %-30s %s', $name, $command->getDescription()));
		}
	}
}
