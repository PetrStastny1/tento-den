<?php

declare(strict_types=1);

namespace App\Model\NameDays;

use App\Core\Clock\Clock;
use App\Core\Database\Transaction;
use App\Model\Events\MonthDay;
use App\Model\Import\DayPageSource;


/**
 * Imports the Slovak name days of one day from the sk.wikipedia day page (its own revision).
 * Fetching and parsing happen before the transaction; an error leaves the stored names untouched.
 */
final class SlovakNameDayImportService
{
	public function __construct(
		private readonly DayPageSource $skDayPages,
		private readonly SlovakNameDayParser $parser,
		private readonly NameDayRepository $nameDays,
		private readonly Transaction $transaction,
		private readonly Clock $clock,
	) {
	}


	/**
	 * @return array{revisionId: int, names: list<string>}
	 * @throws \App\Model\Import\SourceException
	 * @throws NameDayParseException
	 */
	public function import(MonthDay $day): array
	{
		$page = $this->skDayPages->fetch($day);
		$parsed = $this->parser->parse($page->html);
		$now = $this->clock->now();
		$this->transaction->run(fn() => $this->nameDays->replaceForDay($day, 'sk', $parsed, $page->title, $page->revisionId, $now));
		return ['revisionId' => $page->revisionId, 'names' => array_map(fn(ParsedNameDay $n): string => $n->name, $parsed)];
	}
}
