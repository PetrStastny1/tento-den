<?php

declare(strict_types=1);

namespace App\Core\Http;

use App\Core\Clock\Clock;


/**
 * Keeps requests within the Wikimedia rate limit and retries temporary failures:
 * requests are sent serially at most $requestsPerMinute, 429/503 wait for Retry-After
 * (or back off exponentially from 5 s), network errors are retried too.
 */
final class PoliteHttpClient implements HttpClient
{
	private const MinBackoffSeconds = 5;

	private ?float $lastRequestAt = null;


	public function __construct(
		private readonly HttpClient $inner,
		private readonly Clock $clock,
		private readonly int $requestsPerMinute = 200,
		private readonly int $maxAttempts = 4,
	) {
	}


	public function get(string $url, array $headers = []): HttpResponse
	{
		for ($attempt = 1; ; $attempt++) {
			$this->throttle();
			try {
				$response = $this->inner->get($url, $headers);
			} catch (HttpException $e) {
				if ($attempt >= $this->maxAttempts) {
					throw $e;
				}
				$this->clock->sleep($this->backoff($attempt));
				continue;
			}

			if (!in_array($response->status, [429, 503], true)) {
				return $response;
			}
			if ($attempt >= $this->maxAttempts) {
				throw new HttpException("GET $url: HTTP {$response->status} after {$attempt} attempts");
			}
			$this->clock->sleep(max($response->retryAfterSeconds() ?? $this->backoff($attempt), self::MinBackoffSeconds));
		}
	}


	private function throttle(): void
	{
		$now = $this->timestamp();
		$interval = 60 / $this->requestsPerMinute;
		if ($this->lastRequestAt !== null && $now - $this->lastRequestAt < $interval) {
			$this->clock->sleep($interval - ($now - $this->lastRequestAt));
		}
		$this->lastRequestAt = $this->timestamp();
	}


	private function backoff(int $attempt): int
	{
		return self::MinBackoffSeconds * 2 ** ($attempt - 1);
	}


	private function timestamp(): float
	{
		return (float) $this->clock->now()->format('U.u');
	}
}
