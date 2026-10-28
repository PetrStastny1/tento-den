<?php

declare(strict_types=1);

namespace App\Core\Http;


final readonly class HttpResponse
{
	/** @var array<string, string> header names in lower case */
	public array $headers;


	/**
	 * @param array<string, string> $headers
	 */
	public function __construct(
		public int $status,
		array $headers,
		public string $body,
	) {
		$this->headers = array_change_key_case($headers, CASE_LOWER);
	}


	public function header(string $name): ?string
	{
		return $this->headers[strtolower($name)] ?? null;
	}


	/**
	 * Seconds from a numeric Retry-After header; null when absent or given as a date.
	 */
	public function retryAfterSeconds(): ?int
	{
		$value = $this->header('Retry-After');
		return $value !== null && ctype_digit(trim($value)) ? (int) trim($value) : null;
	}
}
