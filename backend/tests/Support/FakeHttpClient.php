<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Http\HttpClient;
use App\Core\Http\HttpException;
use App\Core\Http\HttpResponse;


/**
 * Returns queued responses (or throws queued exceptions) and records requested URLs.
 */
final class FakeHttpClient implements HttpClient
{
	/** @var list<string> */
	public array $requests = [];

	/** @var list<HttpResponse|HttpException> */
	private array $queue;


	public function __construct(HttpResponse|HttpException ...$queue)
	{
		$this->queue = array_values($queue);
	}


	public function get(string $url, array $headers = []): HttpResponse
	{
		$this->requests[] = $url;
		$next = array_shift($this->queue) ?? throw new \LogicException("Unexpected request: $url");
		if ($next instanceof HttpException) {
			throw $next;
		}
		return $next;
	}
}
