<?php

declare(strict_types=1);

namespace App\Model\Wikipedia;

use App\Core\Clock\Clock;
use App\Core\Http\HttpClient;
use App\Core\Http\HttpException;
use App\Model\Import\SourceException;
use App\Model\StateDays\ArticleSummary;
use App\Model\StateDays\ArticleSummarySource;
use Nette\Utils\Json;
use Nette\Utils\JsonException;


/**
 * REST page/summary: the plain-text introduction (`extract`) and the revision it comes from.
 * Redirects are followed by the endpoint; the canonical title is kept for attribution.
 */
final class RestSummarySource implements ArticleSummarySource
{
	private const Endpoint = 'https://cs.wikipedia.org/api/rest_v1/page/summary/';


	public function __construct(
		private readonly HttpClient $http,
		private readonly Clock $clock,
	) {
	}


	public function fetch(string $title): ?ArticleSummary
	{
		try {
			$response = $this->http->get(self::Endpoint . rawurlencode(str_replace(' ', '_', $title)), ['Accept' => 'application/json']);
		} catch (HttpException $e) {
			throw new SourceException($e->getMessage(), 0, $e);
		}
		if ($response->status === 404) {
			return null;
		}
		if ($response->status !== 200) {
			throw new SourceException("Summary of „{$title}“: HTTP {$response->status}");
		}
		try {
			$data = Json::decode($response->body, forceArrays: true);
		} catch (JsonException $e) {
			throw new SourceException("Summary of „{$title}“: invalid JSON", 0, $e);
		}

		$canonical = $data['titles']['normalized'] ?? $data['title'] ?? null;
		$extract = is_string($data['extract'] ?? null) ? trim($data['extract']) : '';
		$revision = $data['revision'] ?? null;
		if (($data['type'] ?? null) !== 'standard' || !is_string($canonical) || $extract === '' || !is_numeric($revision)) {
			throw new SourceException("Summary of „{$title}“: unexpected response (type " . Json::encode($data['type'] ?? null) . ')');
		}
		return new ArticleSummary($canonical, $extract, (int) $revision, $this->clock->now());
	}
}
