<?php

declare(strict_types=1);

namespace App\Model\Import;

use App\Core\Clock\Clock;
use App\Core\Database\Transaction;
use App\Model\Events\ArticleInfo;
use App\Model\Events\ArticleRepository;
use App\Model\Events\DayPageParser;
use App\Model\Events\EventMatcher;
use App\Model\Events\EventRepository;
use App\Model\Events\EventStatus;
use App\Model\Events\IncomingEvent;
use App\Model\Events\MatchPlan;
use App\Model\Events\MonthDay;
use App\Model\Events\ParsedEvent;
use App\Model\Events\PrimaryArticleSelector;
use App\Model\Events\ReviewRole;
use App\Model\Events\Section;
use App\Model\NameDays\NameDayParser;
use App\Model\NameDays\NameDayRepository;
use Tracy\ILogger;


/**
 * Imports the events and name days of one day: fetch → parse → resolve articles → match → store.
 * Name days come from the same page revision and are replaced as a whole.
 *
 * Failure never looks like success: everything that can fail (network, parsing,
 * suspicious result) happens before the transaction, the day is written in one
 * transaction together with its "ok" status, and any error leaves the stored
 * events untouched and records "error" for the day.
 */
final class DayImportService
{
	/** a page losing more than half of its active events is more likely broken than edited */
	private const MinKeptRatio = 0.5;
	private const MinEventsForRatioCheck = 4;


	public function __construct(
		private readonly DayPageSource $dayPages,
		private readonly ArticleInfoSource $articles,
		private readonly DayPageParser $parser,
		private readonly PrimaryArticleSelector $primarySelector,
		private readonly EventMatcher $matcher,
		private readonly EventRepository $eventRepository,
		private readonly ArticleRepository $articleRepository,
		private readonly ImportStatusRepository $statusRepository,
		private readonly ImportLockRepository $lock,
		private readonly Transaction $transaction,
		private readonly Clock $clock,
		private readonly ILogger $logger,
		private readonly NameDayParser $nameDayParser,
		private readonly NameDayRepository $nameDayRepository,
	) {
	}


	/**
	 * @throws ImportFailedException
	 */
	public function import(MonthDay $day, ?int $runId = null): DayImportResult
	{
		if (!$this->lock->acquire($day)) {
			// another process owns this day; do not touch its status
			throw new ImportFailedException("Import dne {$day->format()} už běží v jiném procesu");
		}

		try {
			return $this->importLocked($day, $runId);
		} catch (\Throwable $e) {
			$this->statusRepository->markError($day, $e->getMessage(), $runId, $this->clock->now());
			throw new ImportFailedException("Import dne {$day->format()} selhal: {$e->getMessage()}", 0, $e);
		} finally {
			$this->lock->release($day);
		}
	}


	private function importLocked(MonthDay $day, ?int $runId): DayImportResult
	{
		$page = $this->dayPages->fetch($day);
		$parseResult = $this->parser->parse($page->html, $day);
		foreach ($parseResult->skipped as $skipped) {
			$this->logger->log("Import {$day->format()} (revize {$page->revisionId}): přeskočeno podle pravidla – $skipped", ILogger::INFO);
		}
		$nameDays = $this->nameDayParser->parse($page->html, $day); // items that are not names: see bin/report/name-days.php
		$incoming = $this->resolveArticles($parseResult->events);

		$activeBefore = $this->eventRepository->countActive($day);
		if ($activeBefore >= self::MinEventsForRatioCheck && count($incoming) < $activeBefore * self::MinKeptRatio) {
			throw new \RuntimeException(sprintf(
				'Podezřelý úbytek: stránka má %d událostí, uloženo je %d aktivních – nic neměním',
				count($incoming),
				$activeBefore,
			));
		}

		$plan = $this->matcher->match($this->eventRepository->findForMatching($day), $incoming);
		$now = $this->clock->now();

		$this->transaction->run(function () use ($day, $page, $incoming, $plan, $nameDays, $runId, $now): void {
			$this->articleRepository->upsert($this->uniqueArticles($incoming), $now);
			$this->applyPlan($day, $page, $plan, $now);
			$this->nameDayRepository->replaceForDay($day, 'cs', $nameDays->nameDays, $page->title, $page->revisionId, $now);
			$this->statusRepository->markOk($day, $page->revisionId, count($incoming), $runId, $now);
		});

		$sections = array_count_values(array_map(fn(IncomingEvent $e): string => $e->parsed->section->value, $incoming));
		$updated = count(array_filter($plan->matches, fn($m): bool => !$m->exact));
		return new DayImportResult(
			day: $day,
			revisionId: $page->revisionId,
			czechCount: $sections[Section::Czech->value] ?? 0,
			worldCount: $sections[Section::World->value] ?? 0,
			inserted: count($plan->inserts),
			updated: $updated,
			unchanged: count($plan->matches) - $updated,
			missing: count($plan->missingIds) + count($plan->rejectedIds),
			review: array_sum(array_map(fn($r): int => count($r->incoming), $plan->reviews)),
			restored: count($plan->restoredIds),
			skipped: count($parseResult->skipped),
			nameDays: count($nameDays->nameDays),
		);
	}


	/**
	 * @param list<ParsedEvent> $parsed
	 * @return list<IncomingEvent>
	 */
	private function resolveArticles(array $parsed): array
	{
		$titles = array_values(array_unique(array_merge(...array_map(fn(ParsedEvent $e): array => $e->linkTitles, $parsed))));
		$resolved = $titles === [] ? [] : $this->articles->fetch($titles);

		$incoming = [];
		foreach ($parsed as $event) {
			$articles = [];
			foreach ($event->linkTitles as $title) {
				$article = $resolved[$title] ?? null;
				if ($article !== null) {
					$articles[$article->pageId] ??= $article; // two links may lead to one article
				}
			}
			$articles = array_values($articles);
			$incoming[] = new IncomingEvent($event, $articles, $this->primarySelector->select($articles));
		}
		return $incoming;
	}


	private function applyPlan(MonthDay $day, DayPage $page, MatchPlan $plan, \DateTimeImmutable $now): void
	{
		foreach ($plan->matches as $match) {
			$this->eventRepository->updateFromImport($match->stored, $match->incoming, $page->title, $page->revisionId, $now);
		}
		foreach ($plan->inserts as $event) {
			$this->eventRepository->insert($day, $event, $page->title, $page->revisionId, EventStatus::Active, null, $now);
		}
		foreach ($plan->reviews as $review) {
			$newIds = [];
			$group = null; // the group is named after its first replacement
			foreach ($review->incoming as $event) {
				$id = $this->eventRepository->insert($day, $event, $page->title, $page->revisionId, EventStatus::NeedsReview, null, $now);
				$newIds[] = $id;
				$group ??= $id;
			}
			if ($group === null) {
				continue; // a review case always has incoming events; nothing to do otherwise
			}
			foreach ($newIds as $id) {
				$this->eventRepository->markForReview($id, 'Možná nahrazuje: ' . $this->describeCandidates($review->candidates), $group, ReviewRole::Replacement);
			}
			foreach ($review->candidates as $candidateId => $similarity) {
				$this->eventRepository->markForReview(
					$candidateId,
					sprintf('Možná nahrazena: #%s (revize %d, podobnost %.2f)', implode(', #', $newIds), $page->revisionId, $similarity),
					$group,
					ReviewRole::Original,
				);
			}
		}
		$this->eventRepository->restoreFromReview($plan->restoredIds);
		$this->eventRepository->rejectFromReview(
			$plan->rejectedIds,
			sprintf('Zamítnuto: na stránce se vrátil původní text (revize %d)', $page->revisionId),
			$now,
		);
		$this->eventRepository->markMissing($plan->missingIds, $now);
	}


	/**
	 * @param array<int, float> $candidates
	 */
	private function describeCandidates(array $candidates): string
	{
		$parts = [];
		foreach ($candidates as $id => $similarity) {
			$parts[] = sprintf('#%d (podobnost %.2f)', $id, $similarity);
		}
		return implode(', ', $parts);
	}


	/**
	 * @param list<IncomingEvent> $incoming
	 * @return list<ArticleInfo>
	 */
	private function uniqueArticles(array $incoming): array
	{
		$articles = [];
		foreach ($incoming as $event) {
			foreach ($event->articles as $article) {
				$articles[$article->pageId] = $article;
			}
		}
		return array_values($articles);
	}
}
