<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Model\Events\MonthDay;
use App\Model\Import\DayPage;
use App\Model\Import\DayPageSource;
use App\Model\Import\SourceException;


/**
 * Serves day pages from fixtures; days without a page behave like an outage.
 */
final class FixtureDayPageSource implements DayPageSource
{
	public const Dir = __DIR__ . '/../fixtures/wikimedia';

	/** @var array<string, DayPage> */
	private array $pages = [];


	public function serve(string $day, string $fixture, int $revisionId): self
	{
		$html = file_get_contents(self::Dir . '/' . $fixture);
		if ($html === false) {
			throw new \LogicException("Missing fixture $fixture");
		}
		return $this->serveHtml($day, $html, $revisionId);
	}


	public function serveHtml(string $day, string $html, int $revisionId): self
	{
		$this->pages[$day] = new DayPage(MonthDay::fromString($day)->dayPageTitle(), $revisionId, $html);
		return $this;
	}


	public function fail(string $day): self
	{
		unset($this->pages[$day]);
		return $this;
	}


	public function fetch(MonthDay $day): DayPage
	{
		return $this->pages[$day->format()] ?? throw new SourceException("Wikipedia is down (simulated) for {$day->format()}");
	}
}
