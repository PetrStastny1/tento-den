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
 * GET /api/day?date=MM-DD – the ten cards of a day, newest first (default: today in Europe/Prague).
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

		$input = DayRequest::fromQuery($request->getParameter('date'));
		$day = $input->day ?? $this->dayService->today();
		return new JsonResponse(new DayResponse($day, $this->dayService->cardsForDay($day)));
	}
}
