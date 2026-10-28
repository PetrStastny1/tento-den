<?php

declare(strict_types=1);

namespace App\Model\Wikipedia;

use App\Core\Clock\Clock;
use App\Core\Http\HttpClient;
use App\Core\Http\HttpException;
use App\Model\Import\SourceException;
use Nette\Utils\Json;
use Nette\Utils\JsonException;


/**
 * MediaWiki Action API (cs.wikipedia.org/w/api.php) with the maxlag etiquette:
 * replication lag is reported as HTTP 200 with error code "maxlag" in the body,
 * so the body is checked and the request retried after Retry-After.
 */
final class ActionApiClient
{
	private const Endpoint = 'https://cs.wikipedia.org/w/api.php';
	private const MaxLagSeconds = 5;
	private const MinWaitSeconds = 5;


	public function __construct(
		private readonly HttpClient $http,
		private readonly Clock $clock,
		private readonly int $maxLagAttempts = 4,
	) {
	}


	/**
	 * @param array<string, string> $params
	 * @return array<string, mixed>
	 * @throws SourceException
	 */
	public function query(array $params): array
	{
		$url = self::Endpoint . '?' . http_build_query($params + [
			'format' => 'json',
			'formatversion' => '2',
			'maxlag' => (string) self::MaxLagSeconds,
		]);

		for ($attempt = 1; ; $attempt++) {
			try {
				$response = $this->http->get($url);
			} catch (HttpException $e) {
				throw new SourceException($e->getMessage(), 0, $e);
			}
			if ($response->status !== 200) {
				throw new SourceException("Action API: HTTP {$response->status}");
			}

			try {
				$data = Json::decode($response->body, forceArrays: true);
			} catch (JsonException $e) {
				throw new SourceException('Action API: invalid JSON', 0, $e);
			}
			if (!is_array($data)) {
				throw new SourceException('Action API: unexpected response');
			}

			$error = $data['error'] ?? null;
			if (is_array($error) && ($error['code'] ?? null) === 'maxlag') {
				if ($attempt >= $this->maxLagAttempts) {
					throw new SourceException("Action API: database lag persists after $attempt attempts");
				}
				$this->clock->sleep(max($response->retryAfterSeconds() ?? 0, self::MinWaitSeconds));
				continue;
			}
			if (is_array($error)) {
				throw new SourceException('Action API error: ' . ($error['code'] ?? '?') . ' – ' . ($error['info'] ?? ''));
			}
			return $data;
		}
	}
}
