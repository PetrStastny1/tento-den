<?php

declare(strict_types=1);

namespace App\Core\Config;

use Nette\Schema\Expect;
use Nette\Schema\Processor;
use Nette\Schema\ValidationException;


/**
 * Validates environment variables with Nette Schema before the DI container is built,
 * so a missing or malformed value stops the application with a readable message.
 */
final class EnvironmentConfigLoader
{
	/**
	 * @param array<string, string> $env
	 * @throws InvalidConfigurationException
	 */
	public function load(array $env): AppConfig
	{
		$nonEmpty = Expect::string()->required()->min(1);
		$schema = Expect::structure([
			'APP_ENV' => Expect::anyOf(AppConfig::EnvDevelopment, AppConfig::EnvProduction)->required(),
			'DB_HOST' => $nonEmpty,
			'DB_PORT' => Expect::string()->required()->pattern('\d{1,5}'),
			'DB_NAME' => $nonEmpty,
			'DB_USER' => $nonEmpty,
			'DB_PASSWORD' => $nonEmpty,
			'WIKIMEDIA_CONTACT' => Expect::email()->required(),
		])->otherItems(Expect::mixed());

		try {
			/** @var \stdClass $values */
			$values = (new Processor)->process($schema, $env);
		} catch (ValidationException $e) {
			throw InvalidConfigurationException::fromProblems($e->getMessages());
		}

		return new AppConfig(
			environment: $values->APP_ENV,
			database: new DatabaseConfig(
				host: $values->DB_HOST,
				port: (int) $values->DB_PORT,
				name: $values->DB_NAME,
				user: $values->DB_USER,
				password: $values->DB_PASSWORD,
			),
			wikimediaContact: $values->WIKIMEDIA_CONTACT,
		);
	}
}
