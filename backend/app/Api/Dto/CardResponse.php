<?php

declare(strict_types=1);

namespace App\Api\Dto;

use App\Model\Events\Year;
use App\Model\Selection\Card;
use App\Model\Wikipedia\WikipediaUrl;


/**
 * One card as the API returns it. Text is plain text; every card carries its source revision,
 * history (authors) and license.
 */
final readonly class CardResponse implements \JsonSerializable
{
	public const License = 'CC BY-SA 4.0';
	public const LicenseUrl = 'https://creativecommons.org/licenses/by-sa/4.0/deed.cs';


	public function __construct(
		private Card $card,
	) {
	}


	/**
	 * @return array<string, mixed>
	 */
	public function jsonSerialize(): array
	{
		$event = $this->card->data;
		return [
			'id' => $event->id,
			'date' => $event->day->format(),
			'year' => $event->year,
			'yearLabel' => Year::format($event->year),
			'section' => $event->section->value,
			'text' => $event->text,
			'sensitive' => $this->card->sensitive,
			'stateDay' => $this->card->stateDay === null ? null : new StateDayResponse($this->card->stateDay),
			'anniversary' => $this->card->anniversary === null ? null : ['years' => $this->card->anniversary->years, 'label' => $this->card->anniversary->label()],
			'source' => [
				'title' => $event->sourceTitle,
				'revisionUrl' => WikipediaUrl::revision($event->sourceTitle, $event->sourceRevisionId),
				'historyUrl' => WikipediaUrl::history($event->sourceTitle),
				'license' => self::License,
				'licenseUrl' => self::LicenseUrl,
				'fetchedAt' => $event->fetchedAt->format(DATE_ATOM),
			],
			'article' => $event->primaryTitle === null || $event->primaryRevisionId === null ? null : [
				'title' => $event->primaryTitle,
				'url' => WikipediaUrl::revision($event->primaryTitle, $event->primaryRevisionId),
			],
		];
	}
}
