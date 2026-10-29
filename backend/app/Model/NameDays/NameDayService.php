<?php

declare(strict_types=1);

namespace App\Model\NameDays;

use App\Model\Events\MonthDay;


final class NameDayService
{
	public function __construct(
		private readonly NameDayRepository $nameDays,
		private readonly NameInfoRepository $infos,
	) {
	}


	/**
	 * @return array{cz: ?CountryNameDays, sk: ?CountryNameDays} null for a country without names that day
	 */
	public function forDay(MonthDay $day): array
	{
		return ['cz' => $this->forCountry($day, Country::Czech), 'sk' => $this->forCountry($day, Country::Slovak)];
	}


	private function forCountry(MonthDay $day, Country $country): ?CountryNameDays
	{
		$all = $this->nameDays->findForDay($day, $country);
		if ($all === []) {
			return null;
		}
		$main = array_values(array_filter($all, fn(NameDay $n): bool => $n->line === 1));
		$infos = $this->infos->findByNames($country, array_map(fn(NameDay $n): string => $n->name, $all));
		$variants = NameVariants::find(array_map(fn(NameDay $n): string => $n->name, $all), $infos);
		return new CountryNameDays(
			$country,
			array_map(fn(NameDay $n): array => ['nameDay' => $n, 'info' => $infos[$n->name] ?? null, 'variantOf' => $variants[$n->name] ?? null], $main),
			array_values(array_filter($all, fn(NameDay $n): bool => $n->line > 1)),
			$all[0]->sourceTitle,
			$all[0]->sourceRevisionId,
			$all[0]->fetchedAt,
		);
	}
}
