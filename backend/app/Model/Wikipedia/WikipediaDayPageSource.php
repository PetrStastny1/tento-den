<?php

declare(strict_types=1);

namespace App\Model\Wikipedia;

use App\Core\Http\HttpClient;
use App\Core\Http\HttpException;
use App\Model\Events\MonthDay;
use App\Model\Import\DayPage;
use App\Model\Import\DayPageSource;
use App\Model\Import\SourceException;


/**
 * Asks the Action API for the current revision of the day page, then loads Parsoid HTML
 * of exactly that revision. The REST endpoint does not check that a revision belongs to
 * the title, so the returned Content-Revision-Id is verified as well.
 */
final class WikipediaDayPageSource implements DayPageSource
{
	private const RestEndpoint = 'https://cs.wikipedia.org/api/rest_v1/page/html/';


	public function __construct(
		private readonly ActionApiClient $actionApi,
		private readonly HttpClient $http,
	) {
	}


	public function fetch(MonthDay $day): DayPage
	{
		$title = $day->dayPageTitle();
		$revisionId = $this->currentRevision($title);

		$url = self::RestEndpoint . rawurlencode(str_replace(' ', '_', $title)) . '/' . $revisionId;
		try {
			$response = $this->http->get($url, ['Accept' => 'text/html']);
		} catch (HttpException $e) {
			throw new SourceException($e->getMessage(), 0, $e);
		}
		if ($response->status !== 200) {
			throw new SourceException("Parsoid HTML of „{$title}“ (revision $revisionId): HTTP {$response->status}");
		}
		if ($response->header('Content-Revision-Id') !== (string) $revisionId) {
			throw new SourceException(sprintf(
				'Parsoid HTML of „%s“: expected revision %d, got %s',
				$title,
				$revisionId,
				$response->header('Content-Revision-Id') ?? 'none',
			));
		}

		return new DayPage($title, $revisionId, $response->body);
	}


	private function currentRevision(string $title): int
	{
		$data = $this->actionApi->query(['action' => 'query', 'prop' => 'info', 'titles' => $title]);
		$page = $data['query']['pages'][0] ?? null;
		if (!is_array($page) || isset($page['missing']) || !is_int($page['lastrevid'] ?? null)) {
			throw new SourceException("Day page „{$title}“ not found");
		}
		return $page['lastrevid'];
	}
}
