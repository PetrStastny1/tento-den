<?php

declare(strict_types=1);

namespace App\Api\Health;

use App\Model\Health\HealthService;
use Nette\Application\BadRequestException;
use Nette\Application\IPresenter;
use Nette\Application\Request;
use Nette\Application\Response;
use Nette\Application\Responses\JsonResponse;
use Nette\Http\IResponse;


/**
 * GET /api/health – reports whether the API and its database are up.
 */
final class HealthPresenter implements IPresenter
{
	public function __construct(
		private readonly HealthService $healthService,
		private readonly IResponse $httpResponse,
	) {
	}


	public function run(Request $request): Response
	{
		if (!$request->isMethod('GET')) {
			$this->httpResponse->setHeader('Allow', 'GET');
			throw new BadRequestException('Method not allowed', IResponse::S405_MethodNotAllowed);
		}

		$status = $this->healthService->check();
		$this->httpResponse->setCode($status->healthy ? IResponse::S200_OK : IResponse::S503_ServiceUnavailable);
		return new JsonResponse($status);
	}
}
