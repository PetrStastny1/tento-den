<?php

declare(strict_types=1);

namespace App\Core\Config;


final class InvalidConfigurationException extends \RuntimeException
{
	/**
	 * @param list<string> $problems
	 */
	public static function fromProblems(array $problems): self
	{
		return new self(
			"Neplatná konfigurace prostředí (.env):\n - " . implode("\n - ", $problems),
		);
	}
}
