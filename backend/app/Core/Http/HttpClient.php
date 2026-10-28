<?php

declare(strict_types=1);

namespace App\Core\Http;


interface HttpClient
{
	/**
	 * Returns any HTTP response (including 4xx/5xx); throws only when no response arrived.
	 * @param array<string, string> $headers
	 * @throws HttpException
	 */
	public function get(string $url, array $headers = []): HttpResponse;
}
