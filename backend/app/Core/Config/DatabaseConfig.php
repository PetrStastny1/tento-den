<?php

declare(strict_types=1);

namespace App\Core\Config;


final readonly class DatabaseConfig
{
	public function __construct(
		public string $host,
		public int $port,
		public string $name,
		public string $user,
		#[\SensitiveParameter]
		public string $password,
	) {
	}


	public function dsn(): string
	{
		return "mysql:host={$this->host};port={$this->port};dbname={$this->name};charset=utf8mb4";
	}
}
