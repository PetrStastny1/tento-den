<?php

declare(strict_types=1);

namespace App\Core\Console;


final class Output
{
	/**
	 * @param resource $stdout
	 * @param resource $stderr
	 */
	public function __construct(
		private $stdout,
		private $stderr,
	) {
	}


	public function writeln(string $line = ''): void
	{
		fwrite($this->stdout, $line . "\n");
	}


	public function error(string $line): void
	{
		fwrite($this->stderr, $line . "\n");
	}
}
