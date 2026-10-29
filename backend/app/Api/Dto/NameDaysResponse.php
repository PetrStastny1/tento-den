<?php

declare(strict_types=1);

namespace App\Api\Dto;

use App\Model\NameDays\Country;
use App\Model\NameDays\CountryNameDays;
use App\Model\NameDays\NameDay;
use App\Model\Wikipedia\WikipediaUrl;


/**
 * Name days of a day for both modes (CZ from cs.wikipedia, SK from sk.wikipedia), each with its
 * source revision; a name's meaning carries its own attribution, a bearer links to the Wikipedia of the mode.
 */
final readonly class NameDaysResponse implements \JsonSerializable
{
	/**
	 * @param array{cz: ?CountryNameDays, sk: ?CountryNameDays} $nameDays
	 */
	public function __construct(
		private array $nameDays,
	) {
	}


	/**
	 * @return array{cz: ?array<string, mixed>, sk: ?array<string, mixed>}
	 */
	public function jsonSerialize(): array
	{
		return [
			'cz' => $this->nameDays['cz'] === null ? null : $this->country($this->nameDays['cz']),
			'sk' => $this->nameDays['sk'] === null ? null : $this->country($this->nameDays['sk']),
		];
	}


	/**
	 * @return array<string, mixed>
	 */
	private function country(CountryNameDays $days): array
	{
		$language = $days->country->language();
		return [
			'country' => $days->country->value,
			'label' => $days->country->label(),
			'language' => $language,
			'heading' => $days->country === Country::Slovak ? 'Meniny má' : 'Svátek má',
			'names' => array_map(function (array $entry) use ($language): array {
				$meaning = $entry['info']?->meaning;
				$bearer = $entry['info']?->bearer;
				return [
					'name' => $entry['nameDay']->name,
					'note' => $entry['nameDay']->note,
					'meaning' => $meaning === null ? null : [
						'title' => $meaning->title,
						'extract' => $meaning->extract,
						'url' => WikipediaUrl::revision($meaning->title, $meaning->revisionId, $language),
						'source' => $this->source($meaning->title, $meaning->revisionId, $language, $meaning->fetchedAt),
					],
					'bearer' => $bearer === null ? null : [
						'name' => $bearer->name,
						'wikidataId' => $bearer->wikidataItem,
						'url' => WikipediaUrl::article($bearer->article, $language),
					],
				];
			}, $days->names),
			'otherNames' => array_map(fn(NameDay $n): array => ['name' => $n->name, 'note' => $n->note], $days->otherNames),
			'source' => $this->source($days->sourceTitle, $days->sourceRevisionId, $language, $days->fetchedAt),
		];
	}


	/**
	 * @return array<string, string>
	 */
	private function source(string $title, int $revisionId, string $language, \DateTimeImmutable $fetchedAt): array
	{
		return [
			'title' => $title,
			'wiki' => "{$language}.wikipedia.org",
			'revisionUrl' => WikipediaUrl::revision($title, $revisionId, $language),
			'historyUrl' => WikipediaUrl::history($title, $language),
			'license' => CardResponse::License,
			'licenseUrl' => CardResponse::LicenseUrl,
			'fetchedAt' => $fetchedAt->format(DATE_ATOM),
		];
	}
}
