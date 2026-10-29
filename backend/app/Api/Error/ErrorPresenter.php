<?php

declare(strict_types=1);

namespace App\Api\Error;

use App\Api\ApiException;
use Nette\Application\BadRequestException;
use Nette\Application\IPresenter;
use Nette\Application\Request;
use Nette\Application\Response;
use Nette\Application\Responses\JsonResponse;
use Nette\Http\IResponse;
use Tracy\ILogger;


/**
 * Turns every unhandled exception into a JSON error, so API clients never get HTML.
 * Exception details are logged, never sent to the client.
 */
final class ErrorPresenter implements IPresenter
{
	public function __construct(
		private readonly ILogger $logger,
		private readonly IResponse $httpResponse,
	) {
	}


	public function run(Request $request): Response
	{
		$exception = $request->getParameter('exception');

		if ($exception instanceof BadRequestException) {
			$code = $exception->getHttpCode();
		} else {
			$code = IResponse::S500_InternalServerError;
			if ($exception instanceof \Throwable) {
				$this->logger->log($exception, ILogger::EXCEPTION);
			}
		}

		$this->httpResponse->setCode($code);
		return new JsonResponse([
			'error' => [
				'code' => $code,
				// only our own client errors carry a message meant for the client
				'message' => $exception instanceof ApiException ? $exception->getMessage() : (IResponse::ReasonPhrases[$code] ?? 'Error'),
			],
		]);
	}
}
