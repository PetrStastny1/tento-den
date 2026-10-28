<?php

declare(strict_types=1);

namespace App\Core\Config;


/**
 * Validated application configuration built from environment variables.
 */
final readonly class AppConfig
{
	public const EnvDevelopment = 'development';
	public const EnvProduction = 'production';


	public function __construct(
		public string $environment,
		public DatabaseConfig $database,
		public string $wikimediaContact,
	) {
	}


	public function isProduction(): bool
	{
		return $this->environment === self::EnvProduction;
	}


	public function isDebugMode(): bool
	{
		return $this->environment === self::EnvDevelopment;
	}
}
