<?php

declare(strict_types=1);

namespace App\Core\Console;


interface Command
{
	public const ExitOk = 0;
	public const ExitFailure = 1;
	public const ExitUsage = 2;


	public function getName(): string;

	public function getDescription(): string;

	/**
	 * @param array<string, string|true> $options parsed "--name=value" / "--flag"
	 * @return int exit code
	 * @throws UsageException on invalid arguments
	 */
	public function run(array $options, Output $output): int;
}
