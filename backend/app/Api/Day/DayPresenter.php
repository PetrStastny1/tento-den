<?php

declare(strict_types=1);

namespace App\Api\Day;

use App\Api\ApiException;
use App\Api\Dto\DayResponse;
use App\Model\Selection\DayService;
use Nette\Application\IPresenter;
use Nette\Application\Request;
use Nette\Application\Response;
use Nette\Application\Responses\JsonResponse;
use Nette\Http\IResponse;


/**
 * GET /api/day?date=MM-DD[&year=YYYY] – holidays without an event and the ten cards of a day: events
 * commemorated by a state day first, then newest first. Without a date: today in Europe/Prague,
 * including this year's movable holidays; with a date they need the year.
 */
final class DayPresenter implements IPresenter
{
	public function __construct(
		private readonly DayService $dayService,
		private readonly IResponse $httpResponse,
	) {
	}


	public function run(Request $request): Response
	{
		if (!$request->isMethod('GET')) {
			$this->httpResponse->setHeader('Allow', 'GET');
			throw ApiException::methodNotAllowed();
		}

		$input = DayRequest::fromQuery($request->getParameter('date'), $request->getParameter('year'));
		$day = $input->day ?? $this->dayService->today();
		$year = $input->day === null ? ($input->year ?? $this->dayService->currentYear()) : $input->year;
		return new JsonResponse(new DayResponse($day, $this->dayService->day($day, $year)));
	}
}
