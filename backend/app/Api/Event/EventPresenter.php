<?php

declare(strict_types=1);

namespace App\Api\Event;

use App\Api\ApiException;
use App\Api\Dto\EventResponse;
use App\Model\Selection\DayService;
use Nette\Application\IPresenter;
use Nette\Application\Request;
use Nette\Application\Response;
use Nette\Application\Responses\JsonResponse;
use Nette\Http\IResponse;


/**
 * GET /api/events/{id} – one card including its status (active, missing, needs_review).
 */
final class EventPresenter implements IPresenter
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

		$input = EventRequest::fromRoute($request->getParameter('id'));
		$card = $this->dayService->card($input->id) ?? throw ApiException::notFound('Událost nenalezena.');
		return new JsonResponse(new EventResponse($card));
	}
}
