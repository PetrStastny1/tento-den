<?php

declare(strict_types=1);

namespace App\Api\Dto;

use App\Model\Events\MonthDay;
use App\Model\Selection\HolidayCard;
use App\Model\Wikipedia\WikipediaUrl;


/**
 * A holiday card: the official name and type, and the introduction of the Wikipedia article about
 * the day with the same attribution as event cards (null until the summary is downloaded).
 */
final readonly class HolidayResponse implements \JsonSerializable
{
	public function __construct(
		private HolidayCard $holiday,
		private MonthDay $day,
	) {
	}


	/**
	 * @return array<string, mixed>
	 */
	public function jsonSerialize(): array
	{
		$summary = $this->holiday->summary;
		return [
			'date' => $this->day->format(),
			...(new StateDayResponse($this->holiday->stateDay))->jsonSerialize(),
			'article' => $summary === null ? null : [
				'title' => $summary->title,
				'extract' => $summary->extract,
				'url' => WikipediaUrl::revision($summary->title, $summary->revisionId),
				'source' => [
					'title' => $summary->title,
					'revisionUrl' => WikipediaUrl::revision($summary->title, $summary->revisionId),
					'historyUrl' => WikipediaUrl::history($summary->title),
					'license' => CardResponse::License,
					'licenseUrl' => CardResponse::LicenseUrl,
					'fetchedAt' => $summary->fetchedAt->format(DATE_ATOM),
				],
			],
		];
	}
}
